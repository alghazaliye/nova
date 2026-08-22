<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$admin = requireAdminLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get_conversation_messages') {
    requirePermission($admin, 'chats.view');
    $convId = (int)($_GET['conversation_id'] ?? 0);
    $reportMsgId = (int)($_GET['message_id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 50);
    
    if (!$convId) {
        echo json_encode(['success' => false, 'message' => 'رقم المحادثة مطلوب']);
        exit;
    }
    
    try {
        $pdo = getAdminDB();
        
        if ($reportMsgId > 0) {
            // Get messages around the reported message (context)
            // 20 messages before and 10 after for context
            $stmt = $pdo->prepare(
                "SELECT m.*, u.name as sender_name 
                 FROM messages m 
                 LEFT JOIN users u ON u.id = m.sender_id 
                 WHERE m.conversation_id = ? 
                 AND m.created_at <= (SELECT created_at FROM messages WHERE id = ?)
                 ORDER BY m.created_at DESC 
                 LIMIT 30"
            );
            $stmt->execute([$convId, $reportMsgId]);
            $before = $stmt->fetchAll();
            
            $stmt = $pdo->prepare(
                "SELECT m.*, u.name as sender_name 
                 FROM messages m 
                 LEFT JOIN users u ON u.id = m.sender_id 
                 WHERE m.conversation_id = ? 
                 AND m.created_at > (SELECT created_at FROM messages WHERE id = ?)
                 ORDER BY m.created_at ASC 
                 LIMIT 10"
            );
            $stmt->execute([$convId, $reportMsgId]);
            $after = $stmt->fetchAll();
            
            $messages = array_merge(array_reverse($before), $after);
        } else {
            // Just get recent messages
            $stmt = $pdo->prepare(
                "SELECT m.*, u.name as sender_name 
                 FROM messages m 
                 LEFT JOIN users u ON u.id = m.sender_id 
                 WHERE m.conversation_id = ? 
                 ORDER BY m.created_at DESC 
                 LIMIT ?"
            );
            $stmt->execute([$convId, $limit]);
            $messages = array_reverse($stmt->fetchAll());
        }
        
        echo json_encode(['success' => true, 'data' => $messages]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'إجراء غير صالح']);
