FROM python:3.13-slim

WORKDIR /srv

COPY pyproject.toml ./
COPY app ./app
COPY evaluation ./evaluation
# The evaluation extra is large and only the offline report needs it, so a fresh start skips it.
ARG EXTRAS=dev
RUN pip install --no-cache-dir ".[${EXTRAS}]"

COPY tests ./tests

USER nobody
EXPOSE 8001
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001"]
