from fastapi import FastAPI, File, UploadFile, Request
from fastapi.responses import StreamingResponse
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

import io
from io import BytesIO
import traceback
import re

import pypdf

from config import client, make_client

from state import system_state

from models import ChatRequest, RoleRequest

from services.prompt_service import create_chat_instructions

from services.retrieval_service import (
    split_text_into_chunks,
    create_embeddings,
    find_relevant_chunks
)

from services.document_service import create_docx

from services.teacher_tools import (
    get_teacher_instructions,
    get_handout_task,
    get_learning_goal_task,
    get_quiz_task
)

# =========================================================
# FASTAPI APP
# =========================================================

app = FastAPI(title="Moodle AI Smart Backend")


def _get_client(request: Request):
    """
    Gibt einen OpenAI-Client zurueck.
    Wenn im Request ein X-Api-Key-Header gesetzt ist (aus den
    Moodle-Plugin-Einstellungen), wird er bevorzugt.
    Andernfalls greift der Standard-Client aus der .env-Datei.
    """
    key = request.headers.get("x-api-key", "").strip()
    if key:
        return make_client(key)
    return client


# =========================================================
# CORS
# =========================================================

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)


# =========================================================
# GLOBALER ZUSTAND
# =========================================================
# Für einen Uni-Prototypen ausreichend.
# Später sollten verschiedene Browser getrennte Sitzungen erhalten.

# =========================================================
# =========================================================
# TEILNEHMER-VERWALTUNG
# =========================================================

# Wird bei einem Neustart des Backends wieder auf 1 gesetzt.
next_participant_id = 1

# Ordnet jedes Gerät während der aktuellen Sitzung
# dauerhaft einer Teilnehmernummer zu.
participant_by_client = {}

# Die Lehrkraft legt den Chatmodus für die gesamte Sitzung fest.
# Schüler können diesen Modus nicht selbst ändern.
current_chat_mode = "single"

# CHATVERLAUF
# =========================================================

def trim_chat_history() -> None:
    """
    Behält nur die letzten zehn Nachrichten.
    Dazu zählen Fragen und Antworten.
    """

    system_state["chat_history"] = (
        system_state["chat_history"][-10:]
    )


# =========================================================
# DATEI HOCHLADEN
# =========================================================

@app.post("/api/teacher/upload-file")
async def upload_file(request: Request, file: UploadFile = File(...)):
    print("================================")
    print("UPLOAD REQUEST ANGEKOMMEN")
    print("DATEI:", file.filename)
    print("================================")

    try:
        if not file.filename:
            return {
                "status": "error",
                "message": "Die Datei besitzt keinen Dateinamen."
            }

        file_content = await file.read()
        filename_lower = file.filename.lower()

        # TXT-Datei auslesen
        if filename_lower.endswith(".txt"):
            text = file_content.decode(
                "utf-8",
                errors="ignore"
            )

        # PDF-Datei auslesen
        elif filename_lower.endswith(".pdf"):
            pdf_reader = pypdf.PdfReader(
                io.BytesIO(file_content)
            )

            extracted_pages = []

            for page_number, page in enumerate(
                pdf_reader.pages,
                start=1
            ):
                extracted_text = page.extract_text()

                if extracted_text:
                    extracted_pages.append(
                        f"\n--- Seite {page_number} ---\n"
                        f"{extracted_text}"
                    )

            text = "\n".join(extracted_pages)

        else:
            return {
                "status": "error",
                "message": "Nur PDF- oder TXT-Dateien sind erlaubt."
            }

        text = text.strip()

        if not text:
            return {
                "status": "error",
                "message": (
                    "Die Datei enthält keinen lesbaren Text. "
                    "Bei eingescannten PDFs ist möglicherweise "
                    "eine Texterkennung nötig."
                )
            }

        print("TEXT WURDE AUSGELESEN")
        print("TEXTLÄNGE:", len(text))

        # Text in kleine Abschnitte zerlegen
        chunks = split_text_into_chunks(
            text=text,
            chunk_size=1200,
            overlap=200
        )

        if not chunks:
            return {
                "status": "error",
                "message": (
                    "Aus der Datei konnten keine "
                    "Textabschnitte erzeugt werden."
                )
            }

        print("ANZAHL TEXTABSCHNITTE:", len(chunks))
        print("ERSTELLE EMBEDDINGS ...")

        # Fuer jeden Abschnitt einmal ein Embedding erstellen
        chunk_embeddings = create_embeddings(
            chunks,
            openai_client=_get_client(request)
        )

        if len(chunk_embeddings) != len(chunks):
            return {
                "status": "error",
                "message": (
                    "Die Embeddings konnten nicht vollständig "
                    "erstellt werden."
                )
            }

        # Erst nach erfolgreicher Verarbeitung speichern
        system_state["content"] = text
        system_state["filename"] = file.filename
        system_state["chunks"] = chunks
        system_state["chunk_embeddings"] = chunk_embeddings

        # Neues Material bedeutet neuer Chat
        system_state["chat_history"] = []
        system_state["group_chat_history"] = []

        print("DATEI ERFOLGREICH VERARBEITET")
        print("EMBEDDINGS ERSTELLT")
        print("CHATVERLAUF GELÖSCHT")

        return {
            "status": "success",
            "message": f"{file.filename} wurde erfolgreich geladen.",
            "chunks": len(chunks)
        }

    except Exception as error:
        print("UPLOAD FEHLER")
        print(str(error))
        traceback.print_exc()

        return {
            "status": "error",
            "message": (
                "Die Datei konnte nicht verarbeitet werden: "
                f"{str(error)}"
            )
        }


# =========================================================
# BOT-ROLLE ÄNDERN
# =========================================================

@app.post("/api/teacher/set-role")
async def set_bot_role(request: RoleRequest):
    allowed_roles = {
        "standard",
        "lerncoach",
        "einfach"
    }

    if request.role not in allowed_roles:
        return {
            "status": "error",
            "message": "Die gewählte Bot-Rolle ist ungültig."
        }

    system_state["selected_role"] = request.role

    print("BOT-ROLLE GEÄNDERT:", request.role)

    return {
        "status": "success",
        "message": "Die Bot-Rolle wurde geändert."
    }

# =========================================================
# CHATMODUS ÄNDERN
# =========================================================

@app.post("/api/teacher/set-mode")
async def set_chat_mode(request: Request):
    global current_chat_mode

    data = await request.json()
    selected_mode = data.get("mode", "single")

    if selected_mode not in {"single", "group"}:
        return {
            "status": "error",
            "message": "Der gewählte Chatmodus ist ungültig."
        }

    current_chat_mode = selected_mode

    print("CHATMODUS GEÄNDERT:", current_chat_mode)

    return {
        "status": "success",
        "mode": current_chat_mode,
        "message": (
            "Der Chatmodus wurde auf Einzelchat gesetzt."
            if current_chat_mode == "single"
            else "Der Chatmodus wurde auf Gruppenchat gesetzt."
        )
    }


# =========================================================
# AKTUELLEN CHATMODUS ABRUFEN
# =========================================================

@app.get("/api/chat/mode")
async def get_chat_mode():
    return {
        "mode": current_chat_mode
    }


# =========================================================
# GRUPPENCHAT: TEILNEHMER BEITRITT
# =========================================================

@app.post("/api/chat/join")
async def join_group_chat(request: Request):
    global next_participant_id

    client_id = request.client.host

    if client_id not in participant_by_client:
        participant_by_client[client_id] = (
            f"Schüler {next_participant_id}"
        )
        next_participant_id += 1

    participant_id = participant_by_client[client_id]

    print("TEILNEHMER:", participant_id)
    print("GERÄT:", client_id)

    return {
        "participant_id": participant_id
    }

# =========================================================
# CHAT MIT OPENAI
# =========================================================

@app.post("/api/chat")
async def chat_endpoint(request: Request):
    global next_participant_id
    data = await request.json()

    message = str(data.get("message", "")).strip()

    # Der Chatmodus kommt ausschließlich von der Lehrkraft.
    # Ein vom Schüler gesendeter "mode"-Wert wird ignoriert.
    mode = current_chat_mode

    participant_id = data.get("participant_id")

    if mode == "group":
        client_id = request.client.host

        if client_id not in participant_by_client:
            participant_by_client[client_id] = (
                f"Schüler {next_participant_id}"
            )
            next_participant_id += 1

        participant_id = participant_by_client[client_id]

    print("================================")
    print("CHAT ENDPOINT WURDE AUFGERUFEN")
    print("NACHRICHT:", message)
    print("MODUS:", mode)
    print("TEILNEHMER:", participant_id)
    print("ROLLE:", system_state["selected_role"])
    print("================================")

    if not message:
        return {
            "response": "Bitte gib zuerst eine Frage ein."
        }

    if not system_state["chunks"]:
        return {
            "response": "⚠️ Es wurde noch keine Datei hochgeladen."
        }

    # WICHTIG: chat_history wird bereits hier festgelegt (statt erst
    # weiter unten), damit die Variable im except-Block IMMER existiert,
    # egal an welcher Stelle im try-Block ein Fehler auftritt.
    if mode == "group":
        chat_history = system_state["group_chat_history"]
    else:
        chat_history = system_state["chat_history"]

    try:
        relevant_chunks = find_relevant_chunks(
            question=message,
            number_of_chunks=4,
            openai_client=_get_client(request)
        )

        if not relevant_chunks:
            return {
                "response": (
                    "⚠️ Es konnten keine passenden "
                    "Materialabschnitte gefunden werden."
                )
            }

        relevant_material = (
            "\n\n--- ABSCHNITT ---\n\n".join(
                relevant_chunks
            )
        )

        instructions = create_chat_instructions(
            mode=mode,
            role=system_state["selected_role"],
            relevant_material=relevant_material
        )

        if mode == "group":
            chat_history.append({
                "role": "user",
                "content": f"{participant_id}: {message}",
                "participant_id": participant_id
            })

        else:
            chat_history.append({
                "role": "user",
                "content": message
            })

        chat_history[:] = chat_history[-10:]

        model_history = [
            {
                "role": item["role"],
                "content": item["content"]
            }
            for item in chat_history
        ]

        response = _get_client(request).responses.create(
            model="gpt-4o-mini",
            instructions=instructions,
            input=model_history,
            max_output_tokens=500
        )

        bot_answer = response.output_text.strip()

        if mode == "group":
            bot_answer = re.sub(
                r"^Schüler \d+:\s*",
                "",
                bot_answer,
                count=1
            )

        if not bot_answer:
            bot_answer = (
                "Ich konnte gerade keine Textantwort erzeugen. "
                "Bitte versuche es erneut."
            )

        chat_history.append({
            "role": "assistant",
            "content": bot_answer
        })

        chat_history[:] = chat_history[-10:]

        return {
            "response": bot_answer,
            "mode": mode,
            "chat_history": chat_history
        }

    except Exception as error:
        print("OPENAI FEHLER")
        print(str(error))
        traceback.print_exc()

        if (
            chat_history
            and chat_history[-1]["role"] == "user"
        ):
            chat_history.pop()

        return {
            "response": (
                "⚠️ Die KI konnte gerade nicht antworten. "
                "Prüfe das VS-Code-Terminal, deinen API-Key "
                "und dein API-Guthaben."
            )
        }

# =========================================================
# GRUPPENCHAT VERLAUF ABRUFEN
# =========================================================

@app.get("/api/chat/group")
async def get_group_chat():
    return {
        "chat_history": system_state["group_chat_history"]
    }

# =========================================================
# CHAT ZURÜCKSETZEN
# =========================================================

@app.post("/api/chat/reset")
async def reset_chat():
    global next_participant_id

    system_state["chat_history"] = []
    system_state["group_chat_history"] = []
    system_state["content"] = ""
    system_state["filename"] = None
    system_state["chunks"] = []
    system_state["chunk_embeddings"] = []
    system_state["last_learning_goals"] = ""
    participant_by_client.clear()
    next_participant_id = 1

    print("CHATVERLÄUFE UND MATERIAL MANUELL GELÖSCHT")

    return {
        "status": "success",
        "message": "Die Unterrichtseinheit wurde zurückgesetzt."
    }

# =========================================================
# LEHRER-TOOLS
# =========================================================

@app.post("/api/tools/{tool_name}")
async def use_tool(tool_name: str, request: Request):
    print("TOOL AUFRUF:", tool_name)

    if not system_state["content"]:
        return {
            "result": "⚠️ Es wurde noch keine Datei hochgeladen."
        }

    try:

        material = system_state["content"][:12000]

        if tool_name == "handout":
            task = get_handout_task()

        elif tool_name == "lernzieltracker":
            task = get_learning_goal_task()

        elif tool_name == "livequiz":
            task = get_quiz_task()

        else:
            return {
                "result": "Dieses Werkzeug ist nicht bekannt."
            }

        instructions = get_teacher_instructions()

        input_text = f"""
BEREITGESTELLTES MATERIAL:
\"\"\"
{material}
\"\"\"

AUFGABE:
{task}
"""

        response = _get_client(request).responses.create(
            model="gpt-4o-mini",
            instructions=instructions,
            input=input_text,
            max_output_tokens=1000
        )

        result = response.output_text.strip()

        if not result:
            result = "Es konnte kein Ergebnis erzeugt werden."

          # Lernzieltracker für späteren Word-Download speichern
        if tool_name == "lernzieltracker":
            system_state["last_learning_goals"] = result

        # Handout und Live-Quiz direkt als Word-Datei herunterladen
        if tool_name in ["handout", "livequiz"]:

            title = "Handout" if tool_name == "handout" else "Live-Quiz"
            filename = "Handout.docx" if tool_name == "handout" else "Live-Quiz.docx"

            document = create_docx(
                title=title,
                content=result
            )

            return StreamingResponse(
                document,
                media_type="application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                headers={
                    "Content-Disposition":
                    f'attachment; filename="{filename}"'
                }
            )

        # Alle anderen Lehrerwerkzeuge liefern weiterhin Text zurück
        return {
            "result": result
        }
    except Exception as error:

        print("TOOL FEHLER")
        print(str(error))
        traceback.print_exc()

        return {
            "result":
            "⚠️ Bei der KI-Generierung ist ein Fehler aufgetreten."
        }


# =========================================================
# LERNZIELTRACKER ALS WORD HERUNTERLADEN
# =========================================================

@app.post("/api/tools/lernzieltracker/download")
async def download_learning_goals():

    content = system_state.get("last_learning_goals", "").strip()

    if not content:
        return {
            "status": "error",
            "message": "Es wurde noch kein Lernzieltracker erstellt."
        }

    document = create_docx(
        title="Lernzieltracker",
        content=content
    )

    return StreamingResponse(
        document,
        media_type="application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        headers={
            "Content-Disposition":
            'attachment; filename="Lernzieltracker.docx"'
        }
    )

# =========================================================
# STATUS-SEITE
# =========================================================

@app.get("/")
async def root():
    return {
        "status": "Backend läuft",
        "ki": "OpenAI API aktiv",
        "datei_geladen": bool(system_state["content"]),
        "dateiname": system_state["filename"],
        "rolle": system_state["selected_role"],
        "chatmodus": current_chat_mode,
        "anzahl_abschnitte": len(system_state["chunks"]),
        "nachrichten_im_verlauf": len(
            system_state["chat_history"]
        )
    }