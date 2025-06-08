from flask import Flask, request
import requests
import os

app = Flask(__name__)

BOT_TOKEN = os.environ.get("7961262765:AAFKtvksPxrCL_9eZ9Oe8fcHv4Z0e8-PkBQ")  # تنظیم از متغیر Railway
BASE_URL = f"https://api.telegram.org/bot{7961262765:AAFKtvksPxrCL_9eZ9Oe8fcHv4Z0e8-PkBQ}"

@app.route("/")
def home():
    return "Bot is running."

@app.route("/webhook", methods=["POST"])
def webhook():
    data = request.get_json()

    if "message" in data:
        chat_id = data["message"]["chat"]["id"]
        message = data["message"].get("text", "")
        
        reply = f"شما گفتید: {message}"
        requests.post(f"{BASE_URL}/sendMessage", json={
            "chat_id": chat_id,
            "text": reply
        })
    return "ok"

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port)
