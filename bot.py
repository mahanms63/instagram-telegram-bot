from flask import Flask, request
from telegram import Bot, Update
import os

TOKEN = os.environ.get("BOT_TOKEN", "7961262765:AAFKtvksPxrCL_9eZ9Oe8fcHv4Z0e8-PkBQ")  # یا مستقیم بذار برای تست
bot = Bot(token=TOKEN)

app = Flask(__name__)

@app.route('/')
def index():
    return 'Bot is running!'

@app.route('/webhook', methods=['POST'])
def webhook():
    update = Update.de_json(request.get_json(force=True), bot)
    chat_id = update.message.chat.id
    text = update.message.text
    bot.send_message(chat_id=chat_id, text=f"شما نوشتید: {text}")
    return 'ok'


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port)
