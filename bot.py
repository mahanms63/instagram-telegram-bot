from flask import Flask, request
from telegram import Bot, Update
from telegram.ext import Dispatcher, MessageHandler, filters, CallbackContext
import os

# دریافت توکن از متغیر محیطی (در Railway باید تعریف شود)
TOKEN = os.environ.get('7961262765:AAFKtvksPxrCL_9eZ9Oe8fcHv4Z0e8-PkBQ')
if not TOKEN:
    raise ValueError("توکن ربات در متغیر محیطی BOT_TOKEN تعریف نشده است.")

# ساخت شیء Bot و Flask
bot = Bot(token=TOKEN)
app = Flask(__name__)

# ساخت Dispatcher
dispatcher = Dispatcher(bot=bot, update_queue=None, workers=0, use_context=True)

# هندلر پیام‌های متنی
def handle_message(update: Update, context: CallbackContext):
    chat_id = update.effective_chat.id
    text = update.message.text
    context.bot.send_message(chat_id=chat_id, text=f"پیام شما: {text}")

# افزودن هندلر به Dispatcher
dispatcher.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_message))

# بررسی وضعیت (برای تست زنده بودن بات)
@app.route('/')
def index():
    return 'ربات فعال است.'

# Endpoint برای دریافت Webhook از تلگرام
@app.route('/webhook', methods=['POST'])
def webhook():
    update = Update.de_json(request.get_json(force=True), bot)
    dispatcher.process_update(update)
    return 'ok', 200

# اجرای برنامه Flask
if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port)
