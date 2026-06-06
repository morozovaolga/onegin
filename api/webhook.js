import fs from 'fs';
import path from 'path';

const readLines = (filename) => {
  const filePath = path.join(process.cwd(), filename);
  try {
    return fs
      .readFileSync(filePath, 'utf8')
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean);
  } catch (err) {
    console.error('Read error', filePath, err.message);
    return [];
  }
};

const lines = {
  first: readLines('1st.txt'),
  second: readLines('2nd.txt'),
  third: readLines('3rd.txt'),
  fourth: readLines('4th.txt'),
};

const keyboard = {
  inline_keyboard: [[{ text: '🥟 Еще один пирожок', callback_data: 'new_message' }]],
};

const rand = (arr) => arr[Math.floor(Math.random() * arr.length)] || '';

const generateMessage = () => {
  if (!lines.first.length || !lines.second.length || !lines.third.length || !lines.fourth.length) {
    return '❌ Ошибка: один из файлов пуст или не найден';
  }
  return [rand(lines.first), rand(lines.second), rand(lines.third), rand(lines.fourth)].join('\n');
};

export default async function handler(req, res) {
  if (req.method !== 'POST') return res.status(200).send('ok');

  const arr = req.body;
  const TOKEN = process.env.TG_TOKEN;

  const send = async (chat_id, text, reply_markup) => {
    await fetch(`https://api.telegram.org/bot${TOKEN}/sendMessage`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ chat_id, text, parse_mode: 'HTML', reply_markup }),
    });
  };

  if (arr.message) {
    const chat_id = arr.message.chat.id;
    const text = arr.message.text ?? '';
    if (text === '/start') {
      await send(chat_id, 'Привет! Жми на кнопку, чтобы получить пирожок:', keyboard);
    } else {
      await send(chat_id, generateMessage(), keyboard);
    }
  }

  if (arr.callback_query) {
    const chat_id = arr.callback_query.message.chat.id;
    const query_id = arr.callback_query.id;
    await send(chat_id, generateMessage(), keyboard);
    await fetch(`https://api.telegram.org/bot${TOKEN}/answerCallbackQuery`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ callback_query_id: query_id }),
    });
  }

  res.status(200).send('ok');
}