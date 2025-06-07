# I'll now prepare a complete bot script in Python that the user can upload to a GitHub repository or deploy directly.
bot_script = """
# bot.py

import os
import requests
from flask import Flask, request
from telegram import Bot, Update
from telegram.ext import Dispatcher, MessageHandler, Filters

app = Flask(__name__)
TOKEN = os.environ.get("BOT_TOKEN")
bot = Bot(token=TOKEN)
dispatcher = Dispatcher(bot, None, workers=0, use_context=True)

# Simple extractor using an unofficial public Instagram downloader (no API key needed)
def extract_instagram_video(insta_url):
    try:
        api_url = "https://saveig.app/api/ajaxSearch"
        headers = {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
            "Origin": "https://saveig.app",
            "Referer": "https://saveig.app/en",
            "User-Agent": "Mozilla/5.0"
        }
        data = f"q={insta_url}&t=media"
        response = requests.post(api_url, headers=headers, data=data)
        response.raise_for_status()
        result = response.json()
        if result["links"]:
            return result["links"][0]["url"]
    except Exception as e:
        print("Error extracting video:", e)
    return None

def handle_message(update, context):
    text = update.message.text
    if "instagram.com" in text:
        update.message.reply_text("⏳ در حال دریافت ویدیو از اینستاگرام...")
        video_url = extract_instagram_video(text)
        if video_url:
            context.bot.send_video(chat_id=update.effective_chat.id, video=video_url)
        else:
            update.message.reply_text("❌ متاسفانه نتونستم ویدیو رو پیدا کنم. لطفاً مطمئن شو لینک عمومی هست.")
    else:
        update.message.reply_text("سلام! لینک پست یا ریلز اینستاگرام رو برام بفرست.")

dispatcher.add_handler(MessageHandler(Filters.text & ~Filters.command, handle_message))

@app.route("/webhook", methods=["POST"])
def webhook():
    update = Update.de_json(request.get_json(force=True), bot)
    dispatcher.process_update(update)
    return "ok"

@app.route("/")
def home():
    return "ربات تلگرام فعال است."

if __name__ == "__main__":
    app.run(port=5000)
"""

requirements_txt = """
python-telegram-bot==13.15
Flask==2.3.2
requests==2.31.0
"""

procfile_content = "web: python bot.py"

