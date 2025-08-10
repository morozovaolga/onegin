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

// === Генерация случайного пирожка ===
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

// === Работа с голосами и топ-10 ===
function loadVotes() {
    if (!file_exists('votes.json')) return [];
    $data = json_decode(file_get_contents('votes.json'), true);
    return $data ?: [];
}

function saveVotes($votes) {
    // Оставляем только с голосами > 0
    $votes = array_filter($votes, fn($item) => $item['votes'] > 0);

    // Сортируем по убыванию голосов
    usort($votes, fn($a, $b) => $b['votes'] <=> $a['votes']);

    // Оставляем только топ-10
    $votes = array_slice($votes, 0, 10);

    file_put_contents('votes.json', json_encode($votes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function addVote($text) {
    $votes = loadVotes();

    foreach ($votes as &$item) {
        if ($item['text'] === $text) {
            $item['votes']++;
            saveVotes($votes);
            return;
        }
    }

    // Если нет, добавляем с 1 голосом
    $votes[] = ['text' => $text, 'votes' => 1];
    saveVotes($votes);
}

function getTop10Text() {
    $votes = loadVotes();
    if (empty($votes)) {
        return "Пока никто не голосовал за пирожки.";
    }
    $text = "🏆 <b>Топ 10 пирожков</b>:\n\n";
    foreach ($votes as $i => $item) {
        $text .= ($i+1) . ". " . htmlspecialchars($item['text']) . " — 👍 {$item['votes']}\n\n";
    }
    return $text;
}

// === Основная логика бота ===
$body = file_get_contents('php://input'); 
$arr = json_decode($body, true); 
$tg = new TG('ТОКЕН_БОТА'); 

if (isset($arr['message'])) {
    $chat_id = $arr['message']['chat']['id'];
    $text = $arr['message']['text'] ?? '';

    if ($text === '/start') {
        $msg = "Привет! Жми на кнопку, чтобы получить пирожок:";
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => '🥟 Еще один пирожок', 'callback_data' => 'new_message']],
                [['text' => '🏆 Рейтинг пирожков', 'callback_data' => 'rating']]
            ]
        ]);
        $tg->send($chat_id, $msg, $keyboard);

    } else {
        $msg = generateMessage();
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => '👍 Проголосовать', 'callback_data' => 'vote:' . md5($msg)]],
                [['text' => '🥟 Еще один пирожок', 'callback_data' => 'new_message']],
                [['text' => '🏆 Рейтинг пирожков', 'callback_data' => 'rating']]
            ]
        ]);
        $tg->send($chat_id, $msg, $keyboard);
    }
}

if (isset($arr['callback_query'])) {
    $query_id = $arr['callback_query']['id'];
    $chat_id = $arr['callback_query']['message']['chat']['id'];
    $callback_data = $arr['callback_query']['data'];
    $current_text = $arr['callback_query']['message']['text'];

    if ($callback_data === 'new_message') {
        $new_text = generateMessage();
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => '👍 Проголосовать', 'callback_data' => 'vote:' . md5($new_text)]],
                [['text' => '🥟 Еще один пирожок', 'callback_data' => 'new_message']],
                [['text' => '🏆 Рейтинг пирожков', 'callback_data' => 'rating']]
            ]
        ]);
        $tg->send($chat_id, $new_text, $keyboard);
        $tg->request('answerCallbackQuery', ['callback_query_id' => $query_id]);
    }
    elseif (strpos($callback_data, 'vote:') === 0) {
        addVote($current_text);
        $tg->request('answerCallbackQuery', [
            'callback_query_id' => $query_id,
            'text' => 'Спасибо за голос!'
        ]);
    }
    elseif ($callback_data === 'rating') {
        $tg->send($chat_id, getTop10Text());
        $tg->request('answerCallbackQuery', ['callback_query_id' => $query_id]);
    }
}

exit('ok');
