<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$admin = requireAdminLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get_conversation_messages') {
    requirePermission($admin, 'chats.view');
    $convId = (int)($_GET['conversation_id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 50);
    
    if (!$convId) {
        echo json_encode(['success' => false, 'message' => 'رقم المحادثة مطلوب']);
        exit;
    }
    
    try {
        $pdo = getAdminDB();
        $stmt = $pdo->prepare(
            "SELECT m.*, u.name as sender_name 
             FROM messages m 
             LEFT JOIN users u ON u.id = m.sender_id 
             WHERE m.conversation_id = ? 
             ORDER BY m.created_at DESC 
             LIMIT ?"
        );
        $stmt->execute([$convId, $limit]);
        $messages = $stmt->fetchAll();
        
        // Reverse to show in chronological order
        $messages = array_reverse($messages);
        
        echo json_encode(['success' => true, 'data' => $messages]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'إجراء غير صالح']);
