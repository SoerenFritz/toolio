from fastapi import FastAPI, File, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import pypdf
import io
import traceback
import ollama   # bleibt drin, aber wir nutzen es erstmal nicht

# --------------------------------
# APP START
# --------------------------------

app = FastAPI(title="Moodle AI Smart Backend")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --------------------------------
# GLOBAL STATE
# --------------------------------

system_state = {
    "content": "",
    "selected_role": "standard"
}

# --------------------------------
# MODELS
# --------------------------------

class ChatRequest(BaseModel):
    message: str
    mode: str


class RoleRequest(BaseModel):
    role: str


# --------------------------------
# FILE UPLOAD
# --------------------------------

@app.post("/api/teacher/upload-file")
async def upload_file(file: UploadFile = File(...)):

    print("================================")
    print("UPLOAD REQUEST ANGEKOMMEN")
    print("DATEI:", file.filename)
    print("================================")

    try:
        file_content = await file.read()

        print("DATEI GELESEN")

        # TXT
        if file.filename.lower().endswith(".txt"):

            print("TXT DATEI ERKANNT")

            text = file_content.decode("utf-8", errors="ignore")

        # PDF
        elif file.filename.lower().endswith(".pdf"):

            print("PDF DATEI ERKANNT")

            pdf_reader = pypdf.PdfReader(io.BytesIO(file_content))

            text_list = []

            for page in pdf_reader.pages:
                extracted = page.extract_text()

                if extracted:
                    text_list.append(extracted)

            text = "\n".join(text_list)

        else:
            print("FALSCHES FORMAT")

            return {
                "status": "error",
                "message": "Nur PDF oder TXT erlaubt."
            }

        system_state["content"] = text.strip()

        print("DATEI ERFOLGREICH GESPEICHERT")

        return {
            "status": "success",
            "message": f"{file.filename} erfolgreich geladen."
        }

    except Exception as e:

        print("UPLOAD FEHLER")
        print(str(e))
        traceback.print_exc()

        return {
            "status": "error",
            "message": str(e)
        }


# --------------------------------
# BOT ROLE
# --------------------------------

@app.post("/api/teacher/set-role")
async def set_bot_role(request: RoleRequest):

    print("BOT ROLLE GEÄNDERT:", request.role)

    system_state["selected_role"] = request.role

    return {
        "status": "success",
        "message": "Bot-Rolle geändert."
    }


# --------------------------------
# CHAT TEST (OHNE OLLAMA)
# --------------------------------

@app.post("/api/chat")
async def chat_endpoint(request: ChatRequest):

    print("================================")
    print("CHAT ENDPOINT WURDE AUFGERUFEN")
    print("NACHRICHT:", request.message)
    print("MODUS:", request.mode)
    print("================================")

    # Prüfen ob Datei vorhanden
    if not system_state["content"]:

        print("KEIN DATEIKONTEXT")

        return {
            "response": "⚠️ Es wurde noch keine Datei hochgeladen."
        }

    print("DATEIKONTEXT VORHANDEN")

    # HARDCODED TEST ANTWORT
    return {
        "response": "✅ TEST ANTWORT VOM PYTHON BACKEND"
    }


# --------------------------------
# TOOLS
# --------------------------------

@app.post("/api/tools/{tool_name}")
async def use_tool(tool_name: str):

    print("TOOL AUFRUF:", tool_name)

    return {
        "result": f"Tool {tool_name} erfolgreich getestet."
    }


# --------------------------------
# ROOT TEST
# --------------------------------

@app.get("/")
async def root():

    return {
        "status": "Backend läuft"
    }