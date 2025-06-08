from flask import Flask, request
import requests
import os

app = Flask(__name__)

TOKEN = os.getenv("7961262765:AAFKtvksPxrCL_9eZ9Oe8fcHv4Z0e8-PkBQ")
URL = f"https://api.telegram.org/bot{TOKEN}"

@app.route('/')
def index():
    return 'Bot is running.'

@app.route('/webhook', methods=["POST"])
def webhook():
    data = request.get_json()
    if "message" in data:
        chat_id = data["message"]["chat"]["id"]
        text = data["message"].get("text", "")
        if "instagram.com" in text:
            send_message(chat_id, "در حال دریافت ویدیو از اینستاگرام...")
            # اینجا لینک دانلود را اضافه کن
        else:
            send_message(chat_id, "لطفاً لینک اینستاگرام را ارسال کنید.")
    return "OK"

def send_message(chat_id, text):
    requests.post(f"{URL}/sendMessage", json={"chat_id": chat_id, "text": text})

if __name__ == "__main__":
    app.run()
