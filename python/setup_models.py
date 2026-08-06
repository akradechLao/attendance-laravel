import os
import sys
import urllib.request
import zipfile
from pathlib import Path

MODELS_DIR = os.path.join(os.path.dirname(__file__), "models")

SHAPE_PREDICTOR_URL = "http://dlib.net/files/shape_predictor_68_face_landmarks.dat.bz2"
FACE_RECOGNITION_URL = "http://dlib.net/files/dlib_face_recognition_resnet_model_v1.dat.bz2"

SHAPE_PREDICTOR_FILE = "shape_predictor_68_face_landmarks.dat"
FACE_RECOGNITION_FILE = "dlib_face_recognition_resnet_model_v1.dat"


def download_file(url: str, dest_path: str) -> bool:
    try:
        print(f"Downloading {os.path.basename(dest_path)}...")
        print(f"URL: {url}")

        def progress_hook(block_num: int, block_size: int, total_size: int) -> None:
            downloaded = block_num * block_size
            if total_size > 0:
                percent = min(100, (downloaded * 100) // total_size)
                sys.stdout.write(f"\r  Progress: {percent}% ({downloaded // (1024*1024)}MB / {total_size // (1024*1024)}MB)")
                sys.stdout.flush()

        urllib.request.urlretrieve(url, dest_path, reporthook=progress_hook)
        print()
        return True
    except Exception as e:
        print(f"\n  Error downloading: {str(e)}")
        return False


def extract_bz2(bz2_path: str, dest_path: str) -> bool:
    try:
        import bz2

        print(f"Extracting {os.path.basename(bz2_path)}...")
        with bz2.open(bz2_path, "rb") as f_in:
            with open(dest_path, "wb") as f_out:
                while True:
                    chunk = f_in.read(8192)
                    if not chunk:
                        break
                    f_out.write(chunk)

        os.remove(bz2_path)
        print(f"  Extracted to {dest_path}")
        return True
    except Exception as e:
        print(f"  Error extracting: {str(e)}")
        return False


def download_models() -> None:
    os.makedirs(MODELS_DIR, exist_ok=True)

    shape_predictor_path = os.path.join(MODELS_DIR, SHAPE_PREDICTOR_FILE)
    face_recognition_path = os.path.join(MODELS_DIR, FACE_RECOGNITION_FILE)

    success = True

    if not os.path.exists(shape_predictor_path):
        bz2_path = shape_predictor_path + ".bz2"
        if download_file(SHAPE_PREDICTOR_URL, bz2_path):
            if not extract_bz2(bz2_path, shape_predictor_path):
                success = False
        else:
            success = False
    else:
        print(f"  {SHAPE_PREDICTOR_FILE} already exists, skipping...")

    if not os.path.exists(face_recognition_path):
        bz2_path = face_recognition_path + ".bz2"
        if download_file(FACE_RECOGNITION_URL, bz2_path):
            if not extract_bz2(bz2_path, face_recognition_path):
                success = False
        else:
            success = False
    else:
        print(f"  {FACE_RECOGNITION_FILE} already exists, skipping...")

    print()
    if success:
        print("All models downloaded successfully!")
        print(f"Models saved to: {MODELS_DIR}")
    else:
        print("Some models failed to download.")
        print()
        print("Manual download instructions:")
        print("=" * 60)
        print()
        print("1. Download shape_predictor_68_face_landmarks.dat:")
        print(f"   URL: {SHAPE_PREDICTOR_URL}")
        print(f"   Save to: {shape_predictor_path}")
        print()
        print("2. Download dlib_face_recognition_resnet_model_v1.dat:")
        print(f"   URL: {FACE_RECOGNITION_URL}")
        print(f"   Save to: {face_recognition_path}")
        print()
        print("You can also download from GitHub mirrors:")
        print("  https://github.com/davisking/dlib-models")
        print()


if __name__ == "__main__":
    print("Face Recognition Model Setup")
    print("=" * 40)
    print()
    print(f"Target directory: {MODELS_DIR}")
    print()
    download_models()
