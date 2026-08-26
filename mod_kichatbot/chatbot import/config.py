import os
from dotenv import load_dotenv
from openai import OpenAI

# =========================================================
# OPENAI UND UMGEBUNGSVARIABLEN
# =========================================================

load_dotenv()

api_key = os.getenv("OPENAI_API_KEY")

if not api_key:
    raise RuntimeError(
        "OPENAI_API_KEY wurde nicht gefunden. "
        "Prüfe deine .env-Datei."
    )

# Standard-Client für Standalone-Betrieb (aus .env).
client = OpenAI(api_key=api_key)


def make_client(api_key_override: str | None = None) -> OpenAI:
    """
    Gibt einen OpenAI-Client zurück.
    Wenn api_key_override übergeben wird (z. B. aus dem
    X-Api-Key-Header der Moodle-Integration), wird er statt
    der .env-Variable verwendet.
    """
    return OpenAI(api_key=api_key_override or api_key)