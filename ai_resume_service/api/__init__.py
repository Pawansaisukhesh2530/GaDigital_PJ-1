"""FastAPI bridge around the frozen Resume Intelligence Engine (v1).

This package is a thin, safe integration boundary. It never modifies the ATS
parser or the Resume Intelligence Layer — it only orchestrates them per request
inside an isolated workspace and shapes the HTTP contract.
"""
