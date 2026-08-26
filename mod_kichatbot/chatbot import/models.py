from pydantic import BaseModel

# =========================================================
# DATENMODELLE
# =========================================================

class ChatRequest(BaseModel):
    message: str
    mode: str = "single"
    participant_id: str = "Schüler 1"


class RoleRequest(BaseModel):
    role: str