import math

from config import client as _default_client
from state import system_state

# =========================================================
# TEXT IN ABSCHNITTE ZERLEGEN
# =========================================================

def split_text_into_chunks(
    text: str,
    chunk_size: int = 1200,
    overlap: int = 200
) -> list[str]:
    """
    Zerlegt einen langen Text in kleinere Abschnitte.

    chunk_size:
        Maximale Länge eines Abschnitts in Zeichen.

    overlap:
        Anzahl der Zeichen, die sich zwei Abschnitte
        überschneiden. Dadurch gehen Zusammenhänge an
        Abschnittsgrenzen nicht so leicht verloren.
    """

    cleaned_text = " ".join(text.split())

    if not cleaned_text:
        return []

    if chunk_size <= overlap:
        raise ValueError(
            "chunk_size muss größer als overlap sein."
        )

    chunks = []
    start = 0
    step_size = chunk_size - overlap

    while start < len(cleaned_text):
        end = start + chunk_size

        chunk = cleaned_text[start:end].strip()

        if chunk:
            chunks.append(chunk)

        start += step_size

    return chunks


# =========================================================
# EMBEDDINGS ERSTELLEN
# =========================================================

def create_embeddings(
    texts: list[str],
    openai_client=None
) -> list[list[float]]:
    """
    Wandelt Textabschnitte in Zahlenvektoren um.
    Inhaltlich ähnliche Texte erhalten ähnliche Vektoren.
    openai_client: optionaler per-Request-Client; fallback auf globalen Client.
    """

    oc = openai_client or _default_client

    if not texts:
        return []

    all_embeddings = []

    # Mehrere Abschnitte pro API-Anfrage verarbeiten.
    # Bei sehr großen Dokumenten entstehen mehrere Pakete.
    batch_size = 100

    for start in range(0, len(texts), batch_size):
        batch = texts[start:start + batch_size]

        response = oc.embeddings.create(
            model="text-embedding-3-small",
            input=batch
        )

        batch_embeddings = [
            item.embedding
            for item in response.data
        ]

        all_embeddings.extend(batch_embeddings)

    return all_embeddings


# =========================================================
# ÄHNLICHKEIT BERECHNEN
# =========================================================

def cosine_similarity(
    vector_a: list[float],
    vector_b: list[float]
) -> float:
    """
    Berechnet die Ähnlichkeit zweier Embeddings.

    Ein höherer Wert bedeutet:
    Die Texte sind thematisch wahrscheinlich ähnlicher.
    """

    if not vector_a or not vector_b:
        return 0.0

    dot_product = sum(
        a * b
        for a, b in zip(vector_a, vector_b)
    )

    length_a = math.sqrt(
        sum(a * a for a in vector_a)
    )

    length_b = math.sqrt(
        sum(b * b for b in vector_b)
    )

    if length_a == 0 or length_b == 0:
        return 0.0

    return dot_product / (length_a * length_b)


# =========================================================
# PASSENDE ABSCHNITTE FINDEN
# =========================================================

def find_relevant_chunks(
    question: str,
    number_of_chunks: int = 4,
    openai_client=None
) -> list[str]:
    """
    Findet die Textabschnitte, die am besten zur Frage passen.
    openai_client: optionaler per-Request-Client; fallback auf globalen Client.
    """

    oc = openai_client or _default_client

    chunks = system_state["chunks"]
    chunk_embeddings = system_state["chunk_embeddings"]

    if not chunks or not chunk_embeddings:
        return []

    question_response = oc.embeddings.create(
        model="text-embedding-3-small",
        input=question
    )

    question_embedding = (
        question_response.data[0].embedding
    )

    scored_chunks = []

    for chunk, chunk_embedding in zip(
        chunks,
        chunk_embeddings
    ):
        similarity = cosine_similarity(
            question_embedding,
            chunk_embedding
        )

        scored_chunks.append({
            "score": similarity,
            "chunk": chunk
        })

    scored_chunks.sort(
        key=lambda item: item["score"],
        reverse=True
    )

    best_chunks = scored_chunks[:number_of_chunks]

    print("PASSENDE ABSCHNITTE:")

    for index, item in enumerate(best_chunks, start=1):
        print(
            f"Abschnitt {index}: "
            f"Ähnlichkeit {item['score']:.4f}"
        )

    return [
        item["chunk"]
        for item in best_chunks
    ]

