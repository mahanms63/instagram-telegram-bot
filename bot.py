# bot.py

import requests
from flask import Flask, request
from telegram import Bot, Update
from telegram.ext import Dispatcher, MessageHandler, Filters

app = Flask(__name__)

# 🛡 توکن ربات شما
TOKEN = '7961262765:AAFKtvksPxrCL_9eZ9Oe8fcHv4Z0e8-PkBQ'

bot = Bot(token=TOKEN)
dispatcher = Dispatcher(bot, None, workers=0, use_context=True)

# 🔎 دانلود از اینستاگرام (پست یا ریلز عمومی)
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
        print("❌ Error extracting video:", e)
    return None

# 📩 هندل پیام‌های دریافتی
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
        update.message.reply_text("📥 لطفاً لینک پست یا ریلز اینستاگرام رو برام بفرست.")

dispatcher.add_handler(MessageHandler(Filters.text & ~Filters.command, handle_message))

@app.route("/webhook", methods=["POST"])
def webhook():
    update = Update.de_json(request.get_json(force=True), bot)
    dispatcher.process_update(update)
    return "ok"

@app.route("/")
def home():
    return "✅ ربات تلگرام فعال است."

if __name__ == "__main__":
    app.run(port=5000)
