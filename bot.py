# bot.py
import os
import requests
from flask import Flask, request
from telegram import Bot, Update
from telegram.ext import Dispatcher, MessageHandler, Filters

# توکن ربات: یا مستقیماً وارد کن یا در متغیر محیطی تنظیمش کن
TOKEN = os.getenv("BOT_TOKEN", "7961262765:AAFKtvksPxrCL_9eZ9Oe8fcHv4Z0e8-PkBQ")

app = Flask(__name__)
bot = Bot(token=TOKEN)
dispatcher = Dispatcher(bot, None, workers=0, use_context=True)

# تابع استخراج لینک ویدیو از API رایگان
def extract_instagram_video(insta_url):
    try:
        headers = {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
            "Origin": "https://saveig.app",
            "Referer": "https://saveig.app/en",
            "User-Agent": "Mozilla/5.0"
        }
        data = f"q={insta_url}&t=media"
        response = requests.post("https://saveig.app/api/ajaxSearch", headers=headers, data=data)
        result = response.json()
        if "links" in result and result["links"]:
            return result["links"][0]["url"]
    except Exception as e:
        print("❌ Error extracting video:", e)
    return None

# هندلر پیام
def handle_message(update, context):
    text = update.message.text
    if "instagram.com" in text:
        update.message.reply_text("⏳ در حال دریافت ویدیو از اینستاگرام...")
        video_url = extract_instagram_video(text)
        if video_url:
            context.bot.send_video(chat_id=update.effective_chat.id, video=video_url)
        else:
            update.message.reply_text("❌ متاسفانه لینک ویدیو پیدا نشد. مطمئن شو پست عمومی باشه.")
    else:
        update.message.reply_text("سلام! لطفاً لینک ریلز یا پست اینستاگرام رو بفرست.")

# اتصال هندلر به دیسپچر
dispatcher.add_handler(MessageHandler(Filters.text & ~Filters.command, handle_message))

# وب‌هوک برای دریافت پیام از تلگرام
@app.route('/webhook', methods=['POST'])
def webhook():
    update = Update.de_json(request.get_json(force=True), bot)
    dispatcher.process_update(update)
    return "ok"

# تست ساده برای اطمینان از اجرای درست
@app.route('/')
def home():
    return '✅ ربات فعال است.'

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=int(os.environ.get('PORT', 5000)))
