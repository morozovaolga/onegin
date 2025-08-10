<?php

class TG {
    public $token = ''; 
  
    public function __construct($token) {
        $this->token = $token; 
    }
      
    public function send($id, $message, $reply_markup = null) {  
        $data = [
            'chat_id' => $id,
            'text'    => $message,
            'parse_mode' => 'HTML',
        ];
        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }
        return $this->request('sendMessage', $data);
    }

    public function edit($id, $message_id, $text, $reply_markup = null) {
        $data = [
            'chat_id' => $id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }
        return $this->request('editMessageText', $data);
    }   
      
    public function request($method, $data = []) {
        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
        $curl = curl_init(); 
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); 
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'TelegramBot (curl)');

        $response = curl_exec($curl); 
      
        if (curl_error($curl)) {
            error_log('cURL Error: ' . curl_error($curl));
            curl_close($curl);
            return ['ok' => false, 'error' => curl_error($curl)];
        }

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl); 

        if ($http_code !== 200) {
            error_log("HTTP Code: $http_code, Response: $response");
        }

        return json_decode($response, true); 
    }
}

// === Генерация пирожка ===
function generateMessage() {
    $firstLines  = file('1st.txt',  FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $secondLines = file('2nd.txt',  FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $thirdLines  = file('3rd.txt',  FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $fourthLines = file('4th.txt',  FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    return trim($firstLines[array_rand($firstLines)]) . "\n"
         . trim($secondLines[array_rand($secondLines)]) . "\n"
         . trim($thirdLines[array_rand($thirdLines)])  . "\n"
         . trim($fourthLines[array_rand($fourthLines)]);
}

// === Работа с лайками ===
function loadVotes() {
    if (!file_exists('votes.json')) {
        file_put_contents('votes.json', json_encode([]));
    }
    return json_decode(file_get_contents('votes.json'), true);
}

function saveVotes($votes) {
    file_put_contents('votes.json', json_encode($votes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// === Получаем данные ===
$body = file_get_contents('php://input'); 
$arr = json_decode($body, true); 

$tg = new TG('TOKEN'); 

if (isset($arr['message'])) {
    $chat_id = $arr['message']['chat']['id'];
    $text = $arr['message']['text'] ?? '';

    if ($text === '/start') {
        $msg = "Привет! Жми на кнопку, чтобы получить пирожок:";
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => '🍩 Еще один пирожок', 'callback_data' => 'new_message']],
                [['text' => '🏆 Рейтинг пирожков', 'callback_data' => 'rating']]
            ]
        ]);
        $tg->send($chat_id, $msg, $keyboard);

    } else {
        $msg = generateMessage();
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => '👍 Проголосовать', 'callback_data' => 'vote_' . md5($msg)]],
                [['text' => '🍩 Еще один пирожок', 'callback_data' => 'new_message']],
                [['text' => '🏆 Рейтинг пирожков', 'callback_data' => 'rating']]
            ]
        ]);
        $tg->send($chat_id, $msg, $keyboard);
    }
}

// === Обработка кнопок ===
if (isset($arr['callback_query'])) {
    $query_id = $arr['callback_query']['id'];
    $chat_id = $arr['callback_query']['message']['chat']['id'];
    $data = $arr['callback_query']['data'];

    // Новое сообщение
    if ($data === 'new_message') {
        $msg = generateMessage();
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => '👍 Проголосовать', 'callback_data' => 'vote_' . md5($msg)]],
                [['text' => '🍩 Еще один пирожок', 'callback_data' => 'new_message']],
                [['text' => '🏆 Рейтинг пирожков', 'callback_data' => 'rating']]
            ]
        ]);
        $tg->send($chat_id, $msg, $keyboard);
        $tg->request('answerCallbackQuery', ['callback_query_id' => $query_id]);
    }

    // Голосование
    if (strpos($data, 'vote_') === 0) {
        $hash = substr($data, 5);
        $votes = loadVotes();
        if (!isset($votes[$hash])) {
            $votes[$hash] = ['count' => 0, 'text' => $arr['callback_query']['message']['text']];
        }
        $votes[$hash]['count']++;
        saveVotes($votes);
        $tg->request('answerCallbackQuery', [
            'callback_query_id' => $query_id,
            'text' => 'Спасибо за голос!'
        ]);
    }

    // Рейтинг
    if ($data === 'rating') {
        $votes = loadVotes();
        if (!$votes) {
            $rating = "Пока никто не голосовал 😢";
        } else {
            uasort($votes, fn($a, $b) => $b['count'] <=> $a['count']);
            $top = array_slice($votes, 0, 10);
            $rating = "🏆 <b>Топ 10 пирожков</b>:\n\n";
            $i = 1;
            foreach ($top as $pie) {
                $rating .= "{$i}. {$pie['text']} — 👍 {$pie['count']}\n\n";
                $i++;
            }
        }
        $tg->send($chat_id, $rating);
        $tg->request('answerCallbackQuery', ['callback_query_id' => $query_id]);
    }
}

exit('ok');
