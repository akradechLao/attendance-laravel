import os
import sys
import base64
import logging
import tempfile
import uuid
from io import BytesIO
from typing import List, Optional

import cv2
import dlib
import face_recognition
import httpx
import numpy as np
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from PIL import Image

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="Face Recognition API", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

LARAVEL_API_URL = os.getenv("LARAVEL_API_URL", "http://127.0.0.1:8000")

dlib_face_detector: Optional[dlib.fhog_object_detector] = None
dlib_face_recognizer: Optional[dlib.face_recognition_model_v1] = None
dlib_shape_predictor: Optional[dlib.shape_predictor] = None


def load_models():
    global dlib_face_detector, dlib_face_recognizer, dlib_shape_predictor

    models_dir = os.path.join(os.path.dirname(__file__), "models")
    shape_predictor_path = os.path.join(models_dir, "shape_predictor_68_face_landmarks.dat")
    face_recognition_path = os.path.join(models_dir, "dlib_face_recognition_resnet_model_v1.dat")

    try:
        if os.path.exists(shape_predictor_path):
            dlib_shape_predictor = dlib.shape_predictor(shape_predictor_path)
            logger.info("Shape predictor loaded successfully")
        else:
            logger.error(f"Shape predictor not found at {shape_predictor_path}")
            return False

        if os.path.exists(face_recognition_path):
            dlib_face_recognizer = dlib.face_recognition_model_v1(face_recognition_path)
            logger.info("Face recognizer loaded successfully")
        else:
            logger.error(f"Face recognizer not found at {face_recognition_path}")
            return False

        dlib_face_detector = dlib.get_frontal_face_detector()
        logger.info("Face detector loaded successfully")

        return True
    except Exception as e:
        logger.error(f"Error loading models: {str(e)}")
        return False


models_loaded = load_models()


class DetectRequest(BaseModel):
    image: str


class VerifyRequest(BaseModel):
    image: str
    employee_id: int


class RegisterImage(BaseModel):
    angle: str
    data: str


class RegisterRequest(BaseModel):
    employee_id: int
    images: List[RegisterImage]


class CompareRequest(BaseModel):
    image1: str
    image2: str


def decode_base64_image(base64_string: str) -> np.ndarray:
    if "," in base64_string:
        base64_string = base64_string.split(",", 1)[1]

    image_bytes = base64.b64decode(base64_string)
    nparr = np.frombuffer(image_bytes, np.uint8)
    image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

    if image is None:
        raise ValueError("Invalid image data")

    return image


def detect_faces(image: np.ndarray) -> list:
    rgb_image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
    faces = dlib_face_detector(rgb_image, 1)
    return faces


def get_face_encoding(image: np.ndarray, face_location) -> np.ndarray:
    rgb_image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)

    shape = dlib_shape_predictor(rgb_image, face_location)
    encoding = dlib_face_recognizer.compute_face_descriptor(rgb_image, shape)
    return np.array(encoding)


def calculate_distance(encoding1: np.ndarray, encoding2: np.ndarray) -> float:
    return float(np.linalg.norm(encoding1 - encoding2))


def encode_image_to_base64(image: np.ndarray) -> str:
    _, buffer = cv2.imencode(".jpg", image)
    return base64.b64encode(buffer).decode("utf-8")


@app.get("/face/health")
async def health_check():
    return {"status": "ok", "model_loaded": models_loaded}


@app.post("/face/detect")
async def detect(request: DetectRequest):
    try:
        image = decode_base64_image(request.image)
    except ValueError:
        raise HTTPException(status_code=400, detail="Invalid image data")

    try:
        faces = detect_faces(image)
        face_list = []
        for face in faces:
            face_list.append({
                "x": face.left(),
                "y": face.top(),
                "width": face.right() - face.left(),
                "height": face.bottom() - face.top(),
            })
        return {"faces": face_list}
    except Exception as e:
        logger.error(f"Error detecting faces: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Detection error: {str(e)}")


@app.post("/face/verify")
async def verify(request: VerifyRequest):
    try:
        image = decode_base64_image(request.image)
    except ValueError:
        raise HTTPException(status_code=400, detail="Invalid image data")

    faces = detect_faces(image)
    if len(faces) == 0:
        raise HTTPException(status_code=400, detail="No face detected in the image")
    if len(faces) > 1:
        raise HTTPException(status_code=400, detail="Multiple faces detected. Please ensure only one face is visible")

    try:
        encoding = get_face_encoding(image, faces[0])
    except Exception as e:
        logger.error(f"Error generating encoding: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Encoding error: {str(e)}")

    try:
        async with httpx.AsyncClient() as client:
            response = await client.get(
                f"{LARAVEL_API_URL}/api/employees/{request.employee_id}/face-data",
                timeout=10.0,
            )

            if response.status_code == 404:
                raise HTTPException(status_code=404, detail="Employee not found")

            if response.status_code != 200:
                raise HTTPException(status_code=503, detail="Could not retrieve employee face data")

            data = response.json()
            reference_encodings = data.get("encodings", [])

            if not reference_encodings:
                return {
                    "verified": False,
                    "score": 0.0,
                    "message": "No reference face data found for this employee",
                }

            best_distance = float("inf")
            for ref_encoding_b64 in reference_encodings:
                ref_encoding_bytes = base64.b64decode(ref_encoding_b64)
                ref_encoding = np.frombuffer(ref_encoding_bytes, dtype=np.float64)
                distance = calculate_distance(encoding, ref_encoding)
                if distance < best_distance:
                    best_distance = distance

            verified = best_distance < 0.6
            return {
                "verified": verified,
                "score": round(1.0 - best_distance, 4),
                "message": "Face verified successfully" if verified else "Face does not match",
            }

    except httpx.RequestError:
        raise HTTPException(status_code=503, detail="Could not connect to Laravel API")
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error verifying face: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Verification error: {str(e)}")


@app.post("/face/register")
async def register(request: RegisterRequest):
    encodings_list = []

    for img_data in request.images:
        try:
            image = decode_base64_image(img_data.data)
        except ValueError:
            raise HTTPException(status_code=400, detail=f"Invalid image data for angle: {img_data.angle}")

        faces = detect_faces(image)
        if len(faces) == 0:
            raise HTTPException(status_code=400, detail=f"No face detected in image for angle: {img_data.angle}")
        if len(faces) > 1:
            raise HTTPException(
                status_code=400,
                detail=f"Multiple faces detected in image for angle: {img_data.angle}. Please ensure only one face is visible",
            )

        try:
            encoding = get_face_encoding(image, faces[0])
            encoding_bytes = encoding.tobytes()
            encoding_b64 = base64.b64encode(encoding_bytes).decode("utf-8")

            encodings_list.append({
                "angle": img_data.angle,
                "encoding": encoding_b64,
            })
        except Exception as e:
            logger.error(f"Error generating encoding for angle {img_data.angle}: {str(e)}")
            raise HTTPException(status_code=500, detail=f"Encoding error for angle {img_data.angle}: {str(e)}")

    return {"encodings": encodings_list}


@app.post("/face/compare")
async def compare(request: CompareRequest):
    try:
        image1 = decode_base64_image(request.image1)
    except ValueError:
        raise HTTPException(status_code=400, detail="Invalid image1 data")

    try:
        image2 = decode_base64_image(request.image2)
    except ValueError:
        raise HTTPException(status_code=400, detail="Invalid image2 data")

    faces1 = detect_faces(image1)
    if len(faces1) == 0:
        raise HTTPException(status_code=400, detail="No face detected in image1")

    faces2 = detect_faces(image2)
    if len(faces2) == 0:
        raise HTTPException(status_code=400, detail="No face detected in image2")

    try:
        encoding1 = get_face_encoding(image1, faces1[0])
        encoding2 = get_face_encoding(image2, faces2[0])
    except Exception as e:
        logger.error(f"Error generating encodings: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Encoding error: {str(e)}")

    distance = calculate_distance(encoding1, encoding2)

    return {
        "distance": round(distance, 4),
        "match": distance < 0.6,
        "threshold": 0.6,
    }


if __name__ == "__main__":
    import uvicorn

    if not models_loaded:
        logger.warning("Models not loaded. Running setup...")
        logger.info("Run 'python setup_models.py' to download required models")

    uvicorn.run(app, host="0.0.0.0", port=8000)
