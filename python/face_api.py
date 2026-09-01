import os
import sys
import base64
import logging
from typing import List, Optional

import cv2
import dlib
import numpy as np
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="Face Recognition API", version="2.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

dlib_face_detector = None
dlib_face_recognizer = None
dlib_shape_predictor = None


def load_models():
    global dlib_face_detector, dlib_face_recognizer, dlib_shape_predictor

    models_dir = os.path.join(os.path.dirname(__file__), "models")
    shape_predictor_path = os.path.join(models_dir, "shape_predictor_68_face_landmarks.dat")
    face_recognition_path = os.path.join(models_dir, "dlib_face_recognition_resnet_model_v1.dat")

    try:
        if os.path.exists(shape_predictor_path):
            dlib_shape_predictor = dlib.shape_predictor(shape_predictor_path)
            logger.info("Shape predictor loaded")
        else:
            logger.error(f"Shape predictor not found: {shape_predictor_path}")
            return False

        if os.path.exists(face_recognition_path):
            dlib_face_recognizer = dlib.face_recognition_model_v1(face_recognition_path)
            logger.info("Face recognizer loaded")
        else:
            logger.error(f"Face recognizer not found: {face_recognition_path}")
            return False

        dlib_face_detector = dlib.get_frontal_face_detector()
        logger.info("Face detector loaded")
        return True
    except Exception as e:
        logger.error(f"Error loading models: {e}")
        return False


models_loaded = load_models()


class EncodeRequest(BaseModel):
    images: List[str]


class DetectRequest(BaseModel):
    image: str


class VerifyRequest(BaseModel):
    image: str
    face_encodings: List[str]


def decode_base64_image(base64_string: str) -> np.ndarray:
    if "," in base64_string:
        base64_string = base64_string.split(",", 1)[1]
    image_bytes = base64.b64decode(base64_string)
    nparr = np.frombuffer(image_bytes, np.uint8)
    image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if image is None:
        raise ValueError("Invalid image data")
    return image


def preprocess_image(image: np.ndarray) -> np.ndarray:
    lab = cv2.cvtColor(image, cv2.COLOR_BGR2LAB)
    l_channel = lab[:, :, 0]
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    lab[:, :, 0] = clahe.apply(l_channel)
    return cv2.cvtColor(lab, cv2.COLOR_LAB2BGR)


def detect_faces(image: np.ndarray, upsample: int = 2, min_face_size: int = 60) -> list:
    processed = preprocess_image(image)
    rgb_image = cv2.cvtColor(processed, cv2.COLOR_BGR2RGB)
    faces = dlib_face_detector(rgb_image, upsample)
    if min_face_size > 0:
        h, w = image.shape[:2]
        min_px = min_face_size * min(h, w) / 640
        faces = [f for f in faces if (f.right() - f.left()) >= min_px and (f.bottom() - f.top()) >= min_px]
    return faces


def get_face_encoding(image: np.ndarray, face_location) -> np.ndarray:
    rgb_image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
    shape = dlib_shape_predictor(rgb_image, face_location)
    encoding = dlib_face_recognizer.compute_face_descriptor(rgb_image, shape)
    return np.array(encoding)


@app.get("/api/face/health")
async def health_check():
    return {"status": "ok", "model_loaded": models_loaded}


@app.post("/api/face/encode")
async def encode_faces(request: EncodeRequest):
    if not models_loaded:
        raise HTTPException(status_code=500, detail="Models not loaded")

    encodings = []
    errors = []
    success_indices = []

    for index, image_data in enumerate(request.images):
        try:
            image = decode_base64_image(image_data)
        except ValueError:
            errors.append({"index": index, "error": "invalid_image", "detail": "Cannot decode image data"})
            continue

        faces = detect_faces(image)
        if len(faces) == 0:
            errors.append({"index": index, "error": "no_face", "detail": "No face detected in image"})
            continue

        if len(faces) > 1:
            largest = max(faces, key=lambda f: (f.right() - f.left()) * (f.bottom() - f.top()))
            faces = [largest]

        try:
            encoding = get_face_encoding(image, faces[0])
            encoding_bytes = encoding.tobytes()
            encoding_b64 = base64.b64encode(encoding_bytes).decode("utf-8")
            encodings.append(encoding_b64)
            success_indices.append(index)
        except Exception as e:
            logger.error(f"Encoding error at index {index}: {e}")
            errors.append({"index": index, "error": "encoding_failed", "detail": str(e)})

    if len(encodings) == 0 and len(errors) > 0:
        raise HTTPException(status_code=422, detail={"message": "No faces could be encoded", "errors": errors})

    return {"encodings": encodings, "indices": success_indices, "errors": errors}


@app.post("/api/face/detect")
async def detect_face(request: DetectRequest):
    if not models_loaded:
        raise HTTPException(status_code=500, detail="Models not loaded")

    try:
        image = decode_base64_image(request.image)
    except ValueError:
        return {"detected": False, "message": "ไม่สามารถอ่านรูปภาพได้"}

    faces = detect_faces(image)
    if len(faces) == 0:
        return {"detected": False, "message": "ไม่พบใบหน้าในรูป กรุณาถ่ายใหม่"}

    if len(faces) > 1:
        largest = max(faces, key=lambda f: (f.right() - f.left()) * (f.bottom() - f.top()))
        faces = [largest]

    try:
        encoding = get_face_encoding(image, faces[0])
        encoding_bytes = encoding.tobytes()
        encoding_b64 = base64.b64encode(encoding_bytes).decode("utf-8")
        return {"detected": True, "encoding": encoding_b64, "message": "พบใบหน้าเรียบร้อย"}
    except Exception as e:
        logger.error(f"Face detection encoding error: {e}")
        return {"detected": False, "message": "ไม่สามารถประมวลผลใบหน้าได้"}


@app.post("/api/face/verify")
async def verify_face(request: VerifyRequest):
    if not models_loaded:
        raise HTTPException(status_code=500, detail="Models not loaded")

    try:
        image = decode_base64_image(request.image)
    except ValueError:
        raise HTTPException(status_code=400, detail="Invalid image data")

    faces = detect_faces(image)
    if len(faces) == 0:
        return {"matched": False, "score": 0.0, "message": "No face detected"}

    if len(faces) > 1:
        largest = max(faces, key=lambda f: (f.right() - f.left()) * (f.bottom() - f.top()))
        faces = [largest]

    try:
        encoding = get_face_encoding(image, faces[0])
    except Exception as e:
        logger.error(f"Encoding error: {e}")
        raise HTTPException(status_code=500, detail=f"Encoding error: {str(e)}")

    best_distance = float("inf")
    for ref_b64 in request.face_encodings:
        try:
            ref_bytes = base64.b64decode(ref_b64)
            ref_encoding = np.frombuffer(ref_bytes, dtype=np.float64)
            distance = float(np.linalg.norm(encoding - ref_encoding))
            if distance < best_distance:
                best_distance = distance
        except Exception:
            continue

    verified = best_distance < 0.52
    logger.info(f"Face verify: distance={round(best_distance, 4)}, matched={verified}")
    return {
        "matched": verified,
        "score": round(1.0 - best_distance, 4),
        "distance": round(best_distance, 4),
        "message": "Face verified" if verified else "Face does not match",
    }


if __name__ == "__main__":
    import uvicorn

    if not models_loaded:
        logger.warning("Models not loaded. Run: python setup_models.py")

    uvicorn.run(app, host="0.0.0.0", port=8001)
