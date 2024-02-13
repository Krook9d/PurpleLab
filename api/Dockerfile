FROM python:3.7

ENV FLASK_DEBUG=development
ENV PYTHONUNBUFFERED=0

COPY . /app
EXPOSE 5000
WORKDIR /app

RUN pip install -q -r requirements.txt

CMD ["python", "app.py"]
