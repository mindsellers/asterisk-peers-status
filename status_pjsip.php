<?php
function getAsteriskPeers() {
    $output = [];
    $return_var = 0;
    
    exec('asterisk -rx "pjsip show contacts" 2>&1', $output, $return_var);
    
    if ($return_var !== 0) {
        $output = [];
        exec('sudo asterisk -rx "pjsip show contacts" 2>&1', $output, $return_var);
    }
    
    return [
        'output' => $output,
        'success' => ($return_var === 0)
    ];
}

function parseSipPeers($output) {
    $peers = [];
    $seen = []; // Для предотвращения дубликатов
    
    foreach ($output as $line) {
        $line = trim($line);
        
        // Пропускаем пустые строки
        if ($line === '') continue;
        
        // Пропускаем разделители
        if (strpos($line, '====') !== false) continue;
        
        // Пропускаем заголовок таблицы
        if (strpos($line, 'Contact:') === 0 && strpos($line, 'Endpoint') !== false) continue;
        
        // Обрабатываем только строки начинающиеся с "Contact:"
        if (strpos($line, 'Contact:') === 0) {
            // Удаляем "Contact: " из начала строки
            $data = trim(substr($line, 8));
            
            // Разбиваем по пробелам
            $parts = preg_split('/\s+/', $data);
            
            // Нужно минимум 4 части: contact_uri, id, state, rtt
            if (count($parts) < 4) continue;
            
            $contact_uri = $parts[0];
            $contact_id = $parts[1];
            $state = $parts[2];
            $rtt = $parts[3];
            
            // Пропускаем если contact_uri не содержит /sip:
            if (strpos($contact_uri, '/sip:') === false) continue;
            
            // Извлекаем имя пира (часть до /sip:)
            $name = '';
            if (preg_match('/^([^\/]+)\/sip:/', $contact_uri, $nameMatches)) {
                $name = trim($nameMatches[1]);
            }
            
            // Пропускаем если имя пустое
            if (empty($name)) continue;
            
            // Извлекаем IP или хост (часть после @)
            $host = 'Не зарегистрирован';
            if (preg_match('/@([\d]{1,3}\.[\d]{1,3}\.[\d]{1,3}\.[\d]{1,3})/', $contact_uri, $ipMatches)) {
                $host = $ipMatches[1];
            } elseif (preg_match('/@([a-zA-Z0-9\.\-]+)(?::|\s|$)/', $contact_uri, $hostMatches)) {
                $host = $hostMatches[1];
            }
            
            // Проверка на дубликаты
            $unique_key = $name . '_' . $host;
            if (isset($seen[$unique_key])) continue;
            $seen[$unique_key] = true;
            
            $dynamic = true;
            $status = 'Unknown';
            $online = false;
            
            // Определение статуса
            if ($state === 'Avail') {
                $status = 'OK';
                $online = true;
            } elseif ($state === 'Unreachable') {
                $status = 'Unreachable';
                $online = false;
            } elseif ($state === 'NonQual') {
                $status = 'Unknown';
                $online = false;
            } elseif ($state === 'Offline') {
                $status = 'Offline';
                $online = false;
            } else {
                $status = 'Unknown';
                $online = false;
            }
            
            // Форматирование статуса
            if ($status == 'OK' && $host != 'Не зарегистрирован') {
                $formatted_status = '🟢 Online';
            } elseif ($status == 'OK' && $host == 'Не зарегистрирован') {
                $formatted_status = '🟡 Registered (no contact)';
                $online = true;
            } elseif ($status == 'Unreachable') {
                $formatted_status = '🔴 Unreachable';
            } elseif ($status == 'Unknown') {
                $formatted_status = '⚪ Unknown';
            } else {
                $formatted_status = '🔴 Offline';
            }
            
            $peers[] = [
                'name' => $name,
                'host' => $host,
                'dynamic' => $dynamic,
                'status' => $status,
                'formatted_status' => $formatted_status,
                'online' => $online
            ];
        }
    }
    
    // Сортировка по имени
    usort($peers, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    return $peers;
}

$result = getAsteriskPeers();

if (!$result['success']) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Активные пиры Asterisk</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                background-color: #f5f5f5;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            h1 {
                color: #333;
                border-bottom: 2px solid #007bff;
                padding-bottom: 10px;
            }
            .error {
                background-color: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
                padding: 10px;
                border-radius: 4px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📞 Активные пиры Asterisk</h1>
            
            <div class="error">
                <strong>Ошибка:</strong> Не удалось подключиться к Asterisk.<br>
                Проверьте, запущен ли Asterisk и есть ли права на выполнение команд.
            </div>
            
            <p style="margin-top: 20px; color: #666; font-size: 12px;">
                Обновлено: <?php echo date('Y-m-d H:i:s'); ?>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$peers = parseSipPeers($result['output']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Активные пиры Asterisk</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .driver-badge {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #007bff;
            color: white;
            padding: 10px;
            text-align: left;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        tr:hover {
            background-color: #f8f9fa;
        }
        .online {
            color: green;
            font-weight: bold;
        }
        .offline {
            color: red;
        }
        .warning {
            color: orange;
        }
        .unknown {
            color: #999;
            font-style: italic;
        }
        .stats {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            📞 Активные пиры Asterisk 
            <span class="driver-badge">PJSIP</span>
        </h1>
        
        <?php if (empty($peers)): ?>
            <p>Нет активных пиров.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Имя пира</th>
                        <th>Хост</th>
                        <th>Тип</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($peers as $peer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($peer['name']); ?></td>
                            <td><?php echo htmlspecialchars($peer['host']); ?></td>
                            <td><?php echo $peer['dynamic'] ? 'Динамический' : 'Статический'; ?></td>
                            <td>
                                <?php if ($peer['online']): ?>
                                    <span class="online"><?php echo $peer['formatted_status']; ?></span>
                                <?php elseif ($peer['status'] == 'Unknown'): ?>
                                    <span class="unknown"><?php echo $peer['formatted_status']; ?></span>
                                <?php elseif ($peer['status'] == 'Unmonitored'): ?>
                                    <span class="unknown"><?php echo $peer['formatted_status']; ?></span>
                                <?php else: ?>
                                    <span class="offline"><?php echo $peer['formatted_status']; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="stats">
                <strong>Статистика:</strong><br>
                Всего пиров: <?php echo count($peers); ?><br>
                Активных: <?php echo count(array_filter($peers, function($p) { 
                    return $p['online']; 
                })); ?><br>
                Неактивных: <?php echo count(array_filter($peers, function($p) { 
                    return !$p['online']; 
                })); ?>
            </div>
        <?php endif; ?>
        
        <p style="margin-top: 20px; color: #666; font-size: 12px;">
            Обновлено: <?php echo date('Y-m-d H:i:s'); ?>
        </p>
    </div>
</body>
</html>
