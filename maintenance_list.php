<?php
// maintenance_list.php - Maintenance Tracking & Management Module
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 1;

// ===========================================
// 1. SELF-HEALING DATABASE MIGRATION
// ===========================================
try {
    $existingCols = $pdo->query("SHOW COLUMNS FROM  maintenance_requests ")->fetchAll(PDO::FETCHFIELD);
    if (empty($existingCols)) {
        $existingCols = $pdo->query("SHOW COLUMNS FROM maintenance_requests")->fetchAll(PDO::FETCH_COLUMN);
    }

    // Update status column to support full maintenance lifecycle
    $pdo->exec("ALTER TABLE maintenance_requests MODIFY COLUMN status ENUM('Reported', 'Under Review', 'Scheduled', 'In Progress', 'On Hold', 'Completed', 'Cancelled', 'Created', 'Forwarded', 'Accepted by Maintenance System', 'Closed') NOT NULL DEFAULT 'Reported'");

    // Migrate old legacy status values
    $pdo->exec("UPDATE maintenance_requests SET status = 'Reported' WHERE status = 'Created'");
    $pdo->exec("UPDATE maintenance_requests SET status = 'Scheduled' WHERE status IN ('Forwarded', 'Accepted by Maintenance System')");
    $pdo->exec("UPDATE maintenance_requests SET status = 'Completed' WHERE status = 'Closed'");

    // Add new columns if missing
    if (!in_array('title', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN title VARCHAR(255) NULL AFTER utility_asset_id");
    }
    if (!in_array('maintenance_type', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN maintenance_type ENUM('Corrective', 'Preventive', 'Routine', 'Emergency', 'Inspection Followup') NOT NULL DEFAULT 'Corrective' AFTER title");
    }
    if (!in_array('progress_percent', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN progress_percent INT NOT NULL DEFAULT 0 AFTER status");
    }
    if (!in_array('assigned_personnel', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN assigned_personnel VARCHAR(150) NULL AFTER progress_percent");
    }
    if (!in_array('assigned_team', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN assigned_team VARCHAR(100) NULL AFTER assigned_personnel");
    }
    if (!in_array('scheduled_start_date', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN scheduled_start_date DATETIME NULL AFTER assigned_team");
    }
    if (!in_array('target_completion_date', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN target_completion_date DATETIME NULL AFTER scheduled_start_date");
    }
    if (!in_array('actual_completion_date', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN actual_completion_date DATETIME NULL AFTER target_completion_date");
    }
    if (!in_array('findings', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN findings TEXT NULL AFTER actual_completion_date");
    }
    if (!in_array('root_cause', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN root_cause TEXT NULL AFTER findings");
    }
    if (!in_array('action_taken', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN action_taken TEXT NULL AFTER root_cause");
    }
    if (!in_array('parts_replaced', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN parts_replaced TEaT NULL AFTER action_taken");
    }
    if (!in_array('maintenance_notes', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN maintenance_notes TEaT NULL AFTER parts_replaced");
    }
    if (!in_array('follow_up_needed', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN follow_up_needed TINYINT(1) DEFAULT 0 AFTER maintenance_notes");
    }
    if (!in_array('follow_up_reason', $existingCols)) {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN follow_up_reason TEXT NULL AFTER follow_up_needed");
    }
} catch (Throwable $e) {
    // silently continue
}

// =========================================
// 2. AJAX ENDPOINTS
// ==========================================
if (isset($_GET['fetch_logs_id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['fetch_logs_id']);
    $stmt = $pdo->prepare("
        SELECT l.*, COALESCE(u.full_name, u.username, 'System') as user_name 
        FROM maintenance_status_logs l 
        LEFT JOIN users u ON l.changed_by = u.id 
        WHERE l.maintenance_request_id = ? 
        ORDER BY l.changed_at DESC
    ");
    $stmt->execute([$id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($logs);
    exit();
}

if (isset($_GET['fetch_record_id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['fetch_record_id']);
    $stmt = $pdo->prepare("
        SELECT‹Š‹K›˜[YH\È\ÜÙ]Û˜[YKK][]WÝ\H\È\ÜÙ]Ý\KK›ØØ][Ûˆ\È\ÜÙ]ÛØØ][Û‹K˜ÛÛ™][Û—ÜÝ]\ÃBˆ”“ÓHXZ[[˜[˜ÙWÜ™\]Y\ÝÈƒBˆQ•“ÒSˆ][]WØ\ÜÙ]ÈHÓˆ‹][]WØ\ÜÙ]ÚYHKšYBˆÒT‘H‹šYHÃBˆŠNÃBˆ	Ý]O™^XÝ]JÉYJNÃBˆ	™XÈH	Ý]O™™]Ú
ÎŽ‘‘UÒÐTÔÓÐÊNÃBˆXÚÈœÛÛ—Ù[˜ÛÙJ	™XÈÎˆÉÙ\œ›Ü‰ÈOˆ	Ô™XÛÜ™›Ý›Ý[™	×JNÃBˆ^]

NÃBŸCBƒB‹ËÈOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOCB‹ËÈËˆÔÕPÕSÓˆS‘T”ÃB‹ËÈOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOCB™\œ›ÜˆH	ÉÎÃB‰ÝXØÙ\ÜÈH	ÉÎÃBƒBšYˆ
	ÔÑT•‘T–ÉÔ‘TUQTÕÓQUÑ	×HOOH	ÔÔÕ	ÊHÃBˆ	XÝ[ÛˆH	ÔÔÕÉØXÝ[Û‰×HÏÈ	ÉÎÃBƒBˆYˆ
	XÝ[ÛˆOOH	ØÜ™X]IÊHÃBˆ	\ÜÙ]ÚYHY[\J	ÔÔÕÉÝ][]WØ\ÜÙ]ÚY	×JHÈ[˜[
	ÔÔÕÉÝ][]WØ\ÜÙ]ÚY	×JHˆ[ÃBˆ	]HHš[J	ÔÔÕÉÝ]I×HÏÈ	ÉÊNÃBˆ	XZ[[˜[˜ÙWÝ\HH	ÔÔÕÉÛXZ[[˜[˜ÙWÝ\I×HÏÈ	ÐÛÜœ™XÝ]™IÎÃBˆ	ÛÝ\˜ÙHH	ÔÔÕÉÜÛÝ\˜ÙI×HÏÈ	Ð\ÜÙ][Ûš]Üš[™ÉÎÃBˆ	š[Üš]HH	ÔÔÕÉÜš[Üš]I×HÏÈ	ÓYY][IÎÃBˆ	ØØ][ÛˆHš[J	ÔÔÕÉÛØØ][Û‰×HÏÈ	ÉÊNÃBˆ	\ØÜš\[ÛˆHš[J	ÔÔÕÉÙ\ØÜš\[Û‰×HÏÈ	ÉÊNÃBˆ	\ÜÚYÛ™YÜ\œÛÛ›™[Hš[J	ÔÔÕÉØ\ÜÚYÛ™YÜ\œÛÛ›™[	×HÏÈ	ÉÊNÃBˆ	\ÜÚYÛ™YÝX[HHš[J	ÔÔÕÉØ\ÜÚYÛ™YÝX[I×HÏÈ	ÉÊNÃBˆ	ØÚY[YÜÝ\Ù]HHY[\J	ÔÔÕÉÜØÚY[YÜÝ\Ù]I×JHÈ	ÔÔÕÉÜØÚY[YÜÝ\Ù]I×Hˆ[ÃBˆ	\™Ù]ØÛÛ\][Û—Ù]HHY[\J	ÔÔÕÉÝ\™Ù]ØÛÛ\][Û—Ù]I×JHÈ	ÔÔÕÉÝ\™Ù]ØÛÛ\][Û—Ù]I×Hˆ[ÃBˆ	Ý]\ÈHY[\J	ÔÔÕÉÜÝ]\É×JHÈ	ÔÔÕÉÜÝ]\É×Hˆ
	ØÚY[YÜÝ\Ù]HÈ	ÔØÚY[Y	Èˆ	Ô™\ÜY	ÊNÃBˆ	›ÙÜ™\Ü×Ü\˜Ù[H[˜[
	ÔÔÕÉÜ›ÙÜ™\Ü×Ü\˜Ù[	×HÏÈ
NÃBƒBˆYˆ
[\J	]JH[\J	\ØÜš\[ÛŠH[\J	ØØ][ÛŠJHÃBˆ	\œ›ÜˆH	ÔX\ÙHš[[ˆ[™\]Z\™YšY[È
]K\ØÜš\[Û‹[™ØØ][ÛŠK‰ÎÃBˆH[ÙHÃBˆžHÃBˆËÈÙ[™\˜]H[š\]YH™\]Y\ÝQˆS•VVVSSKVBˆ	™Yš^H	ÓS•IÈˆ]J	Ö[IÊHˆ	ËIÎÃBˆ	Ý]H	ËOœ™\\™J”ÑSPÕÓÕS•

ŠH”“ÓHXZ[[˜[˜ÙWÜ™\]Y\ÝÈÒT‘H™\]Y\ÝÚYRÑHÈŠNÃBˆ	Ý]O™^XÝ]JÉ™Yš^ˆ	ÉI×JNÃBˆ	ÛÝ[H	Ý]O™™]ÚÛÛ[[Š
H
ÈNÃBˆ	™\]Y\ÝÚYH	™Yš^ˆÝ—ÜY
	ÛÝ[	Ì	ËÕ—ÔQÓQ•
NÃBƒBˆËÈ[œÙ\XZ[[˜[˜ÙH™\]Y\ÝBˆ	Ý]H	ËOœ™\\™JƒBˆS”ÑT•S•ÈXZ[[˜[˜ÙWÜ™\]Y\ÝÈ
Bˆ™\]Y\ÝÚY][]WØ\ÜÙ]ÚY]KXZ[[˜[˜ÙWÝ\KÛÝ\˜ÙK\ØÜš\[Û‹Bˆš[Üš]KØØ][Û‹Ý]\Ë›ÙÜ™\Ü×Ü\˜Ù[\ÜÚYÛ™YÜ\œÛÛ›™[\ÜÚYÛ™YÝX[KBˆØÚY[YÜÝ\Ù]K\™Ù]ØÛÛ\][Û—Ù]CBˆ
HSQTÈ
ËËËËËËËËËËËËËÊCBˆŠNÃBˆ	Ý]O™^XÝ]JÃBˆ	™\]Y\ÝÚY	\ÜÙ]ÚY	]K	XZ[[˜[˜ÙWÝ\K	ÛÝ\˜ÙK	\ØÜš\[Û‹Bˆ	š[Üš]K	ØØ][Û‹	Ý]\Ë	›ÙÜ™\Ü×Ü\˜Ù[	\ÜÚYÛ™YÜ\œÛÛ›™[	\ÜÚYÛ™YÝX[KBˆ	ØÚY[YÜÝ\Ù]K	\™Ù]ØÛÛ\][Û—Ù]CBˆJNÃBˆ	\šYH	ËO›\Ý[œÙ\Y

NÃBƒBˆËÈÙÈ[š]X[Ý]\ÃBˆ	ËOœ™\\™JƒBˆS”ÑT•S•ÈXZ[[˜[˜ÙWÜÝ]\×ÛÙÜÈ
XZ[[˜[˜ÙWÜ™\]Y\ÝÚYÛÜÝ]\Ë™]×ÜÝ]\ËÚ[™ÙYØžK›Ý\ÊHBˆSQTÈ
Ë•SËËÊCBˆŠKO™^XÝ]JÉ\šY	Ý]\Ë	\Ù\’Y“XZ[[˜[˜ÙH\ÚÈ™YÚ\Ý\™Yˆ[š]X[Ý]\ÎˆÉÝ]\ßKˆ—JNÃBƒBˆËÈÜ™X]H[šÈ™XÛÜ™[ˆXZ[[˜[˜ÙWØ\ÜÙ]Û[šÜÈYˆ\ÜÙ]ÜXÚYšYYBˆYˆ
	\ÜÙ]ÚY
HÃBˆ	ËOœ™\\™JƒBˆS”ÑT•QÓ“Ô‘HS•ÈXZ[[˜[˜ÙWØ\ÜÙ]Û[šÜÈ
XZ[[˜[˜ÙWÜ™\]Y\ÝÚY][]WØ\ÜÙ]ÚY
HBˆSQTÈ
ËÊCBˆŠKO™^XÝ]JÉ\šY	\ÜÙ]ÚYJNÃBƒBˆËÈ\]H\ÜÙ]ÛÛ™][ÛˆYˆYÚš[Üš]HÜˆ\™Ù[BˆYˆ
[—Ø\œ˜^J	š[Üš]KÉÒYÚ	Ë	Ñ[Y\™Ù[˜ÞI×JJHÃBˆ	ËOœ™\\™J•TUH][]WØ\ÜÙ]ÈÑUÛÛ™][Û—ÜÝ]\ÈH	Õ[™\ˆXZ[[˜[˜ÙIÈÒT‘HYHÈŠKO™^XÝ]JÉ\ÜÙ]ÚYJNÃBˆCBˆCBƒBˆËÈÙÈÈ\ÝÜžHX›CBˆ	ËOœ™\\™JƒBˆS”ÑT•S•ÈXZ[[˜[˜ÙWÚ\ÝÜžH
XZ[[˜[˜ÙWÜ™\]Y\ÝÚYXÝ[Û‹\™›Ü›YYØžK]Z[ÊHBˆSQTÈ
Ë	Õ\ÚÈÜ™X]Y	ËËÊCBˆŠKO™^XÝ]JÉ\šY	\Ù\’Y“XZ[[˜[˜ÙHÛÜšÈÜ™\ˆ	Þ	]_IÈ
ÉXZ[[˜[˜ÙWÝ\_JHÜ™X]Y[™X\šÙYÉÝ]\ßKˆ—JNÃBƒBˆËÈ[\›˜[›ÝYšXØ][ÛƒBˆ	ËOœ™\\™JƒBˆS”ÑT•S•ÈXZ[[˜[˜ÙWÛ›ÝYšXØ][ÛœÈ
\Ù\—ÚYY\ÜØYÙJHBˆSQTÈ
ËÊCBˆŠKO™^XÝ]JÉ\Ù\’Y“™]ÈXZ[[˜[˜ÙH\ÚÈÞÉ™\]Y\ÝÚYWH	ÞÉ]_IÈ™YÚ\Ý\™Yˆ—JNÃBƒBˆ	ÝXØÙ\ÜÈH“XZ[[˜[˜ÙH\ÚÈÞÉ™\]Y\ÝÚYWHÝXØÙ\ÜÙ[HÜ™X]YHŽÃBˆHØ]Ú
Ñ^Ù\[Ûˆ	JHÃBˆ	\œ›ÜˆH‘˜Z[YÈÜ™X]HXZ[[˜[˜ÙH\ÚÎˆˆˆ	KO™Ù]Y\ÜØYÙJ
NÃBˆCBˆCBˆH[ÙZYˆ
	XÝ[ÛˆOOH	Ý\]WÜÝ]\ÉÊHÃBˆ	YH[˜[
	ÔÔÕÉÚY	×HÏÈ
NÃBˆ	™]ÔÝ]\ÈH	ÔÔÕÉÜÝ]\É×HÏÈ	Ô™\ÜY	ÎÃBˆ	›ÙÜ™\Ü×Ü\˜Ù[HX^
Z[ŠL[˜[
	ÔÔÕÉÜ›ÙÜ™\Ü×Ü\˜Ù[	×HÏÈ
JJNÃBˆ	\ÜÚYÛ™YÜ\œÛÛ›™[Hš[J	ÔÔÕÉØ\ÜÚYÛ™YÜ\œÛÛ›™[	×HÏÈ	ÉÊNÃBˆ	\ÜÚYÛ™YÝX[HHš[J	ÔÔÕÉØ\ÜÚYÛ™YÝX[I×HÏÈ	ÉÊNÃBˆ	ØÚY[YÜÝ\Ù]HHY[\J	ÔÔÕÉÜØÚY[YÜÝ\Ù]I×JHÈ	ÔÔÕÉÜØÚY[YÜÝ\Ù]I×Hˆ[ÃBˆ	\™Ù]ØÛÛ\][Û—Ù]HHY[\J	ÔÔÕÉÝ\™Ù]ØÛÛ\][Û—Ù]I×JHÈ	ÔÔÕÉÝ\™Ù]ØÛÛ\][Û—Ù]I×Hˆ[ÃBˆ	XÝX[ØÛÛ\][Û—Ù]HHY[\J	ÔÔÕÉØXÝX[ØÛÛ\][Û—Ù]I×JHÈ	ÔÔÕÉØXÝX[ØÛÛ\][Û—Ù]I×Hˆ[ÃBˆ	š[™[™ÜÈHš[J	ÔÔÕÉÙš[™[™ÜÉ×HÏÈ	ÉÊNÃBˆ	›ÛÝØØ]\ÙHHš[J	ÔÔÕÉÜ›ÛÝØØ]\ÙI×HÏÈ	ÉÊNÃBˆ	XÝ[Û—ÝZÙ[ˆHš[J	ÔÔÕÉØXÝ[Û—ÝZÙ[‰×HÏÈ	ÉÊNÃBˆ	\×Ü™\XÙYHš[J	ÔÔÕÉÜ\×Ü™\XÙY	×HÏÈ	ÉÊNÃBˆ	XZ[[˜[˜ÙWÛ›Ý\ÈHš[J	ÔÔÕÉÛXZ[[˜[˜ÙWÛ›Ý\É×HÏÈ	ÉÊNÃBˆ	Ý]\×Û›ÝHHš[J	ÔÔÕÉÜÝ]\×Û›ÝI×HÏÈ	ÔÝ]\È\]YžHXÚšXÚX[‹‰ÊNÃBˆ	›ÛÝ×Ý\Û™YYYH\ÜÙ]
	ÔÔÕÉÙ›ÛÝ×Ý\Û™YYY	×JHÈHˆÃBˆ	›ÛÝ×Ý\Ü™X\ÛÛˆHš[J	ÔÔÕÉÙ›ÛÝ×Ý\Ü™X\ÛÛ‰×HÏÈ	ÉÊNÃBƒBˆYˆ
	Yˆ
HÃBˆžHÃBˆËÈ™]ÚÝ\œ™[™XÛÜ™Bˆ	Ý]H	ËOœ™\\™J”ÑSPÕ
ˆ”“ÓHXZ[[˜[˜ÙWÜ™\]Y\ÝÈÒT‘HYHÈŠNÃBˆ	Ý]O™^XÝ]JÉYJNÃBˆ	Ý\œ™[H	Ý]O™™]Ú
ÎŽ‘‘UÒÐTÔÓÐÊNÃBƒBˆYˆ
	Ý\œ™[
HÃBˆ	ÛÝ]\ÈH	Ý\œ™[ÉÜÝ]\É×NÃBƒBˆËÈ]]ËXY\Ý›ÙÜ™\ÜÈ[™ÛÛ\][Ûˆ]HYˆÛÛ\]YBˆYˆ
	™]ÔÝ]\ÈOOH	ÐÛÛ\]Y	ÊHÃBˆ	›ÙÜ™\Ü×Ü\˜Ù[HLÃBˆYˆ
[\J	XÝX[ØÛÛ\][Û—Ù]IÊJHÃBˆ	XÝX[ØÛÛ\][Û—Ù]HH]J	ÖK[KYšNœÉÊNÃBˆCBˆH[ÙZYˆ
	™]ÔÝ]\ÈOOH	Ò[ˆ›ÙÜ™\ÜÉÉIˆ	›ÙÜ™\Ü×Ü\˜Ù[OOH
HÃBˆ	›ÙÜ™\Ü×Ü\˜Ù[HNÃBˆCBƒBˆËÈ\]HXZ[ˆXZ[[˜[˜ÙH™XÛÜ™Bˆ	\]TÝ]H	ËOœ™\\™JƒBˆTUHXZ[[˜[˜ÙWÜ™\]Y\ÝÈÑUBˆÝ]\ÈHËBˆ›ÙÜ™\Ü×Ü\˜Ù[HËBˆ\ÜÚYÛ™YÜ\œÛÛ›™[HËBˆ\ÜÚYÛ™YÝX[HHËBˆØÚY[YÜÝ\Ù]HHËBˆ\™Ù]ØÛÛ\][Û—Ù]HHËBˆXÝX[ØÛÛ\][Û—Ù]HHËBˆš[™[™ÜÈHËBˆ›ÛÝØØ]\ÙHHËBˆXÝ[Û—ÝZÙ[ˆHËBˆ\×Ü™\XÙYHËBˆXZ[[˜[˜ÙWÛ›Ý\ÈHËBˆ›ÛÝ×Ý\Û™YYYHËBˆ›ÛÝ×Ý\Ü™X\ÛÛˆHÃBˆÒT‘HYHÃBˆŠNÃBˆ	\]TÝ]O™^XÝ]JÃBˆ	™]ÔÝ]\ËBˆ	›ÙÜ™\Ü×Ü\˜Ù[Bˆ	\ÜÚYÛ™YÜ\œÛÛ›™[Bˆ	\ÜÚYÛ™YÝX[KBˆ	ØÚY[YÜÝ\Ù]KBˆ	\™Ù]ØÛÛ\][Û—Ù]KBˆ	XÝX[ØÛÛ\][Û—Ù]KBˆ	š[™[™ÜËBˆ	›ÛÝØØ]\ÙKBˆ	XÝ[Û—ÝZÙ[‹Bˆ	\×Ü™\XÙYBˆ	XZ[[˜[˜ÙWÛ›Ý\ËBˆ	›ÛÝ×Ý\Û™YYYBˆ	›ÛÝ×Ý\Ü™X\ÛÛ‹Bˆ	YBˆJNÃBƒBˆËÈ[œÙ\Ý]\ÈÙÈYˆÝ]\ÈÚ[™ÙYÜˆ›Ý\ÈÚ]™[ƒBˆ	ÙÓ›ÝHH	Ý]\×Û›ÝHÎˆ”›ÙÜ™\ÜÈ\]YÈÉ›ÙÜ™\Ü×Ü\˜Ù[KˆÝ]\ÎˆÉ™]ÔÝ]\ßKˆŽÃBˆ	ËOœ™\\™JƒBˆS”ÑT•S•ÈXZ[[˜[˜ÙWÜÝ]\×ÛÙÜÈ
XZ[[˜[˜ÙWÜ™\]Y\ÝÚYÛÜÝ]\Ë™]×ÜÝ]\ËÚ[™ÙYØžK›Ý\ÊCBˆSQTÈ
ËËËËÊCBˆŠKO™^XÝ]JÉY	ÛÝ]\Ë	™]ÔÝ]\Ë	\Ù\’Y	ÙÓ›ÝWJNÃBƒBˆËÈ[œÙ\\ÝÜžH[žCBˆ	ËOœ™\\™JƒBˆS”ÑT•S•ÈXZ[[˜[˜ÙWÚ\ÝÜžH
XZ[[˜[˜ÙWÜ™\]Y\ÝÚYXÝ[Û‹\™›Ü›YYØžK]Z[ÊCBˆSQTÈ
Ë	Ô›ÙÜ™\ÜÈ\]IËËÊCBˆŠKO™^XÝ]JÉY	\Ù\’Y”Ý]\ÎˆÉÛÝ]\ßHOˆÉ™]ÔÝ]\ßH
›ÙÜ™\ÜÎˆÉ›ÙÜ™\Ü×Ü\˜Ù[IJKˆ›Ý\ÎˆÉÙÓ›Ý_H—JNÃBƒBˆËÈ\]H\ÜÙ]ÛÛ™][ÛˆYˆÛÛ\]YÜˆÛ™ÛÚ[™ÃBˆYˆ
Y[\J	Ý\œ™[ÉÝ][]WØ\ÜÙ]ÚY	×JJHÃBˆYˆ
	™]ÔÝ]\ÈOOH	ÐÛÛ\]Y	ÊHÃBˆ	ËOœ™\\™J•TUH][]WØ\ÜÙ]ÈÑUÛÛ™][Û—ÜÝ]\ÈH	ÑÛÛÙ	ÈÒT‘HYHÈŠKO™^XÝ]JÉÝ\œ™[ÉÝ][]WØ\ÜÙ]ÚY	×WJNÃBˆH[ÙZYˆ
	™]ÔÝ]\ÈOOH	Ò[ˆ›ÙÜ™\ÜÉÊHÃBˆ	ËOœ™\\™J•TUH][]WØ\ÜÙ]ÈÑUÛÛ™][Û—ÜÝ]\ÈH	Õ[™\ˆXZ[[˜[˜ÙIÈÒT‘HYHÈŠKO™^XÝ]JÉÝ\œ™[ÉÝ][]WØ\ÜÙ]ÚY	×WJNÃBˆCBˆCBƒBˆ	ÝXØÙ\ÜÈH“XZ[[˜[˜ÙH\ÚÈÞÉÝ\œ™[ÉÝ™\]Y\ÝÚY	×_WH\]YÝXØÙ\ÜÙ[HHŽÃBˆH[ÙHÃBˆ	\œ›ÜˆH“XZ[[˜[˜ÙH\ÚÈ›Ý›Ý[™ˆŽÃBˆCBˆHØ]Ú
Ñ^Ù\[Ûˆ	JHÃBˆ	\œ›ÜˆH•\]H˜Z[Yˆˆˆ	KO™Ù]Y\ÜØYÙJ
NÃBˆCBˆCBˆH[ÙZYˆ
	XÝ[ÛˆOOH	Ù[]IÊHÃBˆ	YH[˜[
	ÔÔÕÉÚY	×HÏÈ
NÃBˆYˆ
	Yˆ
HÃBˆžHÃBˆ	Ý]H	ËOœ™\\™J”ÑSPÕ™\]Y\ÝÚY”“ÓHXZ[[˜[˜ÙWÜ™\]Y\ÝÈÒT‘HYHÈŠNÃBˆ	Ý]O™^XÝ]JÉYJNÃBˆ	™\HH	Ý]O™™]Ú

NÃBˆBˆYˆ
	™\JHÃBˆ	ËOœ™\\™J‘SUH”“ÓHXZ[[˜[˜ÙWÜ™\]Y\ÝÈÒT‘HYHÈŠKO™^XÝ]JÉYJNÃBˆ	ÝXØÙ\ÜÈH“XZ[[˜[˜ÙH™XÛÜ™ÞÉ™\VÉÜ™\]Y\ÝÚY	×_WH[]YˆŽÃBˆCBˆHØ]Ú
Ñ^Ù\[Ûˆ	JHÃBˆ	\œ›ÜˆH‘˜Z[YÈ[]Nˆˆˆ	KO™Ù]Y\ÜØYÙJ
NÃBˆCBˆCBˆCBŸCB
// =========================================
// 4. STATS SUMMARY AGGREGATION
// ===========================================
function getCount($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$statTotal = getCount($pdo, "SELECT COUNT(*) FROM maintenance_requests");
$statReported = getCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('Reported', 'Under Review', 'Created')");
$statActive = getCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('Scheduled', 'In Progress', 'On Hold')");
$statCompleted = getCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('Completed', 'Closed')");
$statOverdue = getCount($pdo, "SELECT COUNT(*) FROM maintenance_requests WHERE status NOT IN ('Completed', 'Cancelled', 'Closed') AND target_completion_date IS NOT NULL AND target_completion_date < NOW()");

// ==========================================
// 5. QUERY FILTERS & PGINATION
// ==========================================
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$source_filter = $_GET['source'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$type_filter = $_GET['maintenance_type'] ?? '';
$asset_filter = intval($_GET['utility_asset_id'] ?? 0);

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(r.title LIKE ? OR r.request_id LIKE ? OR"r.description LIKE ? OR"r.location LIKE ? OR"r.assigned_personnel LIKE ? OR a.name LIKE ? OR a.asset_id LIKE ?)";
    $sw = '%' . $search . '%';
    $params = array_merge($params, [$sw, $sw, $sw, $sw, $sw, $sw, $sw]);
}

if (!empty($status_filter)) {
    if ($status_filter === 'Overdue') {
        $conditions[] = "r.status NOT IN ('Completed', 'Cancelled', 'Closed') AND r.target_completion_date IS NOT NULL AND r.target_completion_date < NOW()";
    } elseif ($status_filter === 'Active') {
        $conditions[] = "r.status IN ('Scheduled', 'In Progress', 'On Hold')";
    } else {
        $conditions[] = "r.status = ?";
        $params[] = $status_filter;
    }
}

if (!empty($priority_filter)) {
    $conditions[] = "r.priority = ?";
    $params[] = $priority_filter;
}

if (!empty($type_filter)) {
    $conditions[] = "r.maintenance_type = ?";
    $params[] = $type_filter;
}

if (!empty($source_filter)) {
    $conditions[] = "r.source = ?";
    $params[] = $source_filter;
}

if ($asset_filter > 0) {
    $conditions[] = "r.utility_asset_id = ?";
    $params[] = $asset_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Count for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM maintenance_requests r
    LEFT JOIN utility_assets a ON r.utility_asset_id = a.id 
    $whereClause
");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $limit));

// Fetch Records
$dataStmt = $pdo->prepare("
    SELECT 
        r.*,
        a.name as asset_name,
        a.utility_type as asset_type,
        a.location as asset_location_default,
        a.condition_status as asset_condition
    FROM maintenance_requests r
    LEFT JOIN utility_assets a ON r.utility_asset_id = a.id
    $whereClause
    ORDER BY 
        CASE 
            WHEN r.priority = 'Emergency' AND"r.status NOT IN ('Completed', 'Cancelled') THEN 1
            WHEN r.priority = 'High' AND r.status NOT IN ('Completed', 'Cancelled') THEN 2
            WHEN r.status = 'In Progress' THEN 3
            WHEN r.status = 'Scheduled' THEN 4
            WHEN r.status IN ('Reported', 'Under Review') THEN 5
            ELSE 6
        END ASC,
        r.created_at DESC\n    LIMIT $limit OFFSET $offset
");
$dataStmt->execute($params);
$requestsList = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve all assets for dropdown in modals
$assetsList = $pdo->query("SELECT id, name, asset_id, location, condition_status FROM utility_assets ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Management & Tracking | Utilities System</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap");

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(8px);
            background: rgba(15, 23, 42, 0.45);
            z-index: 0;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
            max-width: 100%;
        }

        .main-content.collapsed {
            margin-left: 78px;
        }

        .glass-card {
            width: 100%;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 35px 40px;
            color: #1e293b;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.4);
            margin-bottom: 30px;
        }

        .dashboard-header {
            display: flex;
            zjustify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-title h1 {
            color: #0f172a;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title h1 i {
            color: #2563eb;
        }

        .header-title p {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13.5px;
            border: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
            color: #ffffff;
        }

        .btn-test {
            background: #10b9b1;
            color: #ffffff;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-outline {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
        }

        .btn-outline:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .btn-danger-outline {
            background: transparent;
            border: 1px solid #fecaca;
            color: #ef4444;
        }

        .btn-danger-outline:hover {
            background: #fef2f2;
            color: #dc2626;
            border-color: #f87171;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 8px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #ffffff;
            padding: 18px 22px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
        }

        .stat-card .info .label {
            font-size: 12.5px;
            color: #64748b;
            font-weight: 500;
        }

        .stat-card .info .val {
            font-size: 26px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-blue .stat-icon { background: #eff6ff; color: #2563eb; }
        .stat-amber .stat-icon { background: #fffbeb; color: #d97706; }
        .stat-purple .stat-icon { background: #faf5ff; color: #9333ea; }
        .stat-green .stat-icon { background: #f0fdf4; color: #16a34a; }
        .stat-red .stat-icon { background: #fef2f2; color: #dc2626; }

        /* Filter Panel */
        .filter-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: center;
        }

        @media (max-width: 1200px) {
            .filter-panel {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .filter-panel {
                grid-template-columns: 1fr;
            }
        }

        .form-control, .form-select {
            width: 100%;
            padding: 9px 13px;
            border-radius: 9px;
            border: 1px solid #cbd5e1;
            font-size: 13.5px;
            color: #334155;
            background-color: #ffffff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        /* Table Design */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }

        thead {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        th {
            padding: 14px 16px;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        tbody tr:hover {
            background-color: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }

        .badge-reported { background: #fef3c7; color: #b45309; }
        .badge-review { background: #e0e7ff; color: #4338ca; }
        .badge-scheduled { background: #e0f2fe; color: #0369a1; }
        .badge-progress { background: #f3e8ff; color: #7e22ce; }
        .badge-hold { background: #ffedd5; color: #c2410c; }
        .badge-completed { background: #dcfce7; color: #15803d; }
        .badge-cancelled { background: #f1f5f9; color: #64748b; }

        .badge-pLow { background: #f1f5f9; color: #475569; }
        .badge-pMed { background: #e0f2fe; color: #0284c7; }
        .badge-pHigh { background: #fee2e2; color: #dc2626; font-weight: 700; }
        .badge-pEmg { background: #dc2626; color: #ffffff; font-weight: 700; }

        .badge-type {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .overdue-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #dc2626;
            background: #fee2e2;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            margin-top: 3px;
        }

        /* Mini progress bar */
        .progress-bar-container {
            width: 100px;
            height: 6px;
            background: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
            margin-top: 4px;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            border-radius: 99px;
            transition: width 0.3s ease;
        }

        /* Action buttons group */
        .action-btn-group {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            text-decoration: none;
        }

        .action-btn:hover {
            background: #f8fafc;
            color: #2563eb;
            border-color: #bfdbfe;
        }

        .action-btn.btn-update:hover {
            background: #eff6ff;
            color: #2563eb;
            border-color: #93c5fd;
        }

        .action-btn.btn-del:hover {
            background: #fef2f2;
            color: #ef4444;
            border-color: #fca5a5;
        }

        /* Alert notifications */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pagination-links {
            display: flex;
            gap: 6px;
        }

        .page-link {
            padding: 6px 12px;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .page-link:hover, .page-link.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }

        /* Modals */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-backdrop.active {
            display: flex;
        }

        .modal-box {
            background: #ffffff;
            border-radius: 18px;
            width: 100%;
            max-width: 780px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            animation: modalPop 0.25s ease-out;
        }

        .modal-box-lg {
            max-width: 900px;
        }

        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            padding: 22px 28px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close-btn {
            background: transparent;
            border: none;
            font-size: 20px;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close-btn:hover {
            color: #ef4444;
        }

        .modal-body {
            padding: 28px;
        }

        .modal-footer {
            padding: 18px 28px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #f8fafc;
            border-radius: 0 0 18px 18px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 14px;
        }

        .form-group label {
            font-size: 12.5px;
            font-weight: 600;
            color: #475569;
        }

        .form-group label span.req {
            color: #ef4444;
        }

        .form-group small {
            font-size: 11.5px;
            color: #64748b;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        /* Timeline in modal */
        .timeline-container {
            position: relative;
            padding-left: 24px;
            margin-top: 10px;
        }

        .timeline-container::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 6px;
            bottom: 6px;
            width: 2px;
            background: #cbd5e1;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }

        .timeline-dot {
            position: absolute;
            left: -24px;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #3b82f6;
            border: 3px solid #ffffff;
            box-shadow: 0 0 0 2px #3b82f6;
        }

        .timeline-content {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .timeline-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 13.5px;
        }

        .timeline-notes {
            font-size: 13px;
            color: #334155;
            margin-top: 6px;
        }

        /* Dark Theme Support */
        .dark-theme .glass-card {
            background: rgba(30, 41, 59, 0.94);
            border-color: rgba(255, 255, 255, 0.08);
            color: #f8fafc;
        }

        .dark-theme .header-title h1 { color: #f8fafc; }
        .dark-theme .header-title p { color: #94a3b8; }
        .dark-theme .stat-card {
            background: #1e293b;
            border-color: #334155;
        }
        .dark-theme .stat-card .info .val { color: #f8fafc; }
        .dark-theme .stat-card .info .label { color: #94a3b8; }
        .dark-theme .filter-panel { background: #0f172a; border-color: #334155; }
        .dark-theme .form-control, .dark-theme .form-select {
            background: #1e293b;
            border-color: #475569;
            color: #f8fafc;
        }
        .dark-theme .table-responsive { background: #1e293b; border-color: #334155; }
        .dark-theme thead { background: #0f172a; border-color: #334155; }
        .dark-theme th { color: #94a3b8; }
        .dark-theme td { border-color: #334155; color: #cbd5e1; }
        .dark-theme tbody tr:hover { background-color: #0f172a; }
        .dark-theme .action-btn { background: #0f172a; border-color: #334155; color: #cbd5e1; }
        .dark-theme .btn-outline { background: #1e293b; border-color: #475569; color: #cbd5e1; }
        .dark-theme .modal-box { background: #1e293b; color: #f8fafc; }
        .dark-theme .modal-header { border-color: #334155; }
        .dark-theme .modal-header h3 { color: #f8fafc; }
        .dark-theme .modal-footer { background: #0f172a; border-color: #334155; }
        .dark-theme .form-group label { color: #cbd5e1; }
        .dark-theme .timeline-content { background: #0f172a; border-color: #334155; }
        .dark-theme .timeline-title { color: #f8fafc; }
        .dark-theme .timeline-notes { color: #cbd5e1; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="glass-card">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-title">
                <h1><i class="fas fa-tools"></i> Maintenance Tracking & Management</h1>
                <p>Track, schedule, update, and monitor maintenance work orders on registered municipal utility assets.</p>
            </div>
            <div class="header-actions">
                <a href="maintenance_assets_view.php" class="btn btn-outline">
                    <i class="fas fa-layer-group"></i> Assets View
                </a>
                <a href="maintenance_dashboard.php" class="btn btn-outline">
                    <i class="fas fa-chart-pie"></i> Analytics
                </a>
                <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fas fa-plus-circle"></i> New Maintenance Task
                </button>
            </div>
        </div>

        <!-- Feedback Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle fa-lg"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fai fa-exclamation-triangle fa-lg"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card stat-blue">
                <div class="info">
                    <div class="label">Total Tasks</div>
                    <div class="val"><?php echo number_format($statTotal); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            </div>
            <div class="stat-card stat-amber">
                <div class="info">
                    <div class="label">Reported / In Review</div>
                    <div class="val"><?php echo number_format($statReported); ?></div>
                </div>
                <div class="stat-icon"><i class="fai fa-inbox"></i></div>
            </div>
            <div class="stat-card stat-purple">
                <div class="info">
                    <div class="label">Active / In Progress</div>
                    <div class="val"><?php echo number_format($statActive); ?></div>
                </div>
                <div class="stat-icon"><i class="fai fa-wrench"></i></div>
            </div>
            <div class="stat-card stat-green">
                <div class="info">
                    <div class="label">Completed</div>
                    <div class="val"><?php echo number_format($statCompleted); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            </div>
            <div class="stat-card stat-red">
                <div class="info">
                    <div class="label">Overdue Tasks</div>
                    <div class="val"><?php echo number_format($statOverdue); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>

        <!-- Filter Panel -->
        <form method="GET" class="filter-panel">
            <input type="text" name="search" class="form-control" placeholder="Search code, title, asset, technician, location..." value="<?php echo htmlspecialchars($search); ?>">
            
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="Reported" <?php echo $status_filter === 'Reported' ? 'selected' : ''; ?>Reported</option>
                <option value="Under Review" <?php echo $status_filter === 'Under Review' ? 'selected' : ''; ?>Under Review</option>
                <option value="Scheduled" <?php echo $status_filter === 'Scheduled' ? 'selected' : ''; ?>Scheduled</option>
                <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>In Progress</option>
                <option value="On Hold" <?php echo $status_filter === 'On Hold' ? 'selected' : ''; ?>On Hold</option>
                <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>Completed</option>
                <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>Cancelled</option>
                <option value="Overdue" <?php echo $status_filter === 'Overdue' ? 'selected' : ''; ?>Overdue Only</option>
            </select>

            <select name="priority" class="form-select">
                <option value="">All Priorities</option>
                <option value="Low" <?php echo $priority_filter === 'Low' ? 'selected' : ''; ?>Low</option>
                <option value="Medium" <?php echo $priority_filter === 'Medium' ? 'selected' : ''; ?>Medium</option>
                <option value="High" <?php echo $priority_filter === 'High' ? 'selected' : ''; ?>High</option>
                <option value="Emergency" <?php echo $priority_filter === 'Emergency' ? 'selected' : ''; ?>Emergency</option>
            </select>

            <select name="maintenance_type" class="form-select">
                <option value="">All Types</option>
                <option value="Corrective" <?php echo $type_filter === 'Corrective' ? 'selected' : ''; ?>Corrective</option>
                <option value="Preventive" <?php echo $type_filter === 'Preventive' ? 'selected' : ''; ?>Preventive</option>
                <option value="Routine" <?php echo $type_filter === 'Routine' ? 'selected' : ''; ?>Routine</option>
                <option value="Emergency" <?php echo $type_filter === 'Emergency' ? 'selected' : ''; ?>Emergency</option>
                <option value="Inspection Followup" <?php echo $type_filter === 'Inspection Followup' ? 'selected' : ''; ?>Inspection Followup	</option>
            </select>

            <select name="source" class="form-select">
                <option value="">All Sources</option>
                <option value="Asset Monitoring" <?php echo $source_filter === 'Asset Monitoring' ? 'selected' : ''; ?>Asset Monitoring</option>
                <option value="Resident Report" <?php echo $source_filter === 'Resident Report' ? 'selected' : ''; ?>Resident Report</option>
                <option value="Emergency Alert" <?php echo $source_filter === 'Emergency Alert' ? 'selected' : ''; ?>Emergency Alert</option>
                <option value="Scheduled Routine" <?php echo $source_filter === 'Scheduled Routine' ? 'selected' : ''; ?>Scheduled Routine</option>
                <option value="Inspection" <?php echo $source_filter === 'Inspection' ? 'selected' : ''; ?>Inspection</option>
            </select>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fai fa-filter"></i> Apply</button>
                <a href="maintenance_list.php" class="btn btn-outline btn-sm"><i class="fas fa-redo"></i> Reset</a>
            </div>
        </form>

        <!-- Tasks Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Code & Title</th>
                        <th>Target Asset</th>
                        <th>Type / Source</th>
                        <th>Priority</th>
                        <th>Schedule / Target</th>
                        <th>Assigned Crew</th>
                        <th>Progress & Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requestsList)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 45px; color: #94a3b8;">
                                <i class="fas fa-folder-open fa-3x" style="margin-bottom: 12px; display: block; color: #cbd5e1;"></i>
                                No maintenance records matching the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requestsList as $req): 
                            $isOverdue = (!in_array($req['status'], ['Completed', 'Cancelled', 'Closed']) && !empty($req['target_completion_date']) && strtotime($req['target_completion_date']) < time());
                            $progress = intval($req['progress_percent'] ?? 0);
                            
                            // Status badge mapping
                            $statusClass = 'badge-reported';
                            if ($req['status'] === 'Under Review') $statusClass = 'badge-review';
                            elseif ($req['status'] === 'Scheduled') $statusClass = 'badge-scheduled';
                            elseif ($req['status'] === 'In Progress') $statusClass = 'badge-progress';
                            elseif ($req['status'] === 'On Hold') $statusClass = 'badge-hold';
                            elseif (in_array($req['status'], ['Completed', 'Closed'])) $statusClass = 'badge-completed';
                            elseif ($req['status'] === 'Cancelled') $statusClass = 'badge-cancelled';

                            // Priority badge mapping
                            $priClass = 'badge-pMed';
                            if ($req['priority'] === 'Low') $priClass = 'badge-pLow';
                            elseif ($req['priority'] === 'High') $priClass = 'badge-pHigh';
                            elseif ($req['priority'] === 'Emergency') $priClass = 'badge-pEmg';
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;">
                                        <a href="maintenance_view.php?id=<?php echo $req['id']; ?>" style="color: #2563eb; text-decoration: none;">
                                            <?php echo htmlspecialchars($req['request_id']); ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 12.5px; color: #475569; margin-top: 2px; font-weight: 500;">
                                        <?php echo htmlspecialchars($req['title'] ?: substr($req['text'] ?? $req['description'], 0, 45) . '...'); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                                        <i class="fai fa-map-marker-alt"></i> <?php echo htmlspecialchars($req['location']); ?>
                                    </div>
                                </td>

                                <td>
                                    <?php if (!empty($req['asset_name'])): ?>
                                        <div style="font-weight: 600; color: #1e293b; font-size: 13px;">
                                            <?php echo htmlspecialchars($req['asset_name']); ?>
                                        </div>
                                        <div style="font-size: 11.5px; color: #64748b;">
                                            <?php echo htmlspecialchars($req['asset_type'] ?? 'Utility'); ?> &&bull; <span style="font-size:11px; color:#2563eb;">[<?php echo htmlspecialchars($req['asset_condition'] ?? 'Good'); ?>]</span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic; font-size: 12px;">Unlinked / General</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class='badge-type'><?php echo htmlspecialchars($req['maintenance_type'] ?? 'Corrective'); ?></span>
                                   <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                                        Src: <?php echo htmlspecialchars($req['source']); ?>
                                   </div>
                                </td>

                                <td>
                                    <span class='badge <?php echo $priClass; ?>">
                                        <?php if ($req['priority'] === 'Emergency'): ?><i class="fai fa-bolt"></i><?php endif; ?>
                                        <?php echo htmlspecialchars($reqe³priority']); ?>
                                    </span>
                                </td>

                                <td>
                                    <div style="font-size: 12.5px; font-weight: 500;">
                                        Target: <?php echo !empty($req['target_completion_date']) ? date('M d, Y', strtotime($reqe³target_completion_date'])) : '<span style="color:#94a3b8;">Not set</span>'; ?>
                                    </div>
                                    <?php if ($isOverdue): ?>
                                        <div class="overdue-tag">
                                            <i class="fas fa-exclamation-circle"></i> OVERDUE
                                        </div>
                                    <?php elseif (!empty($req['scheduled_start_date'])): ?>
                                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                            Start: <?php echo date('M d, Y', strtotime($req['scheduled_start_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div style="font-size: 12.5px; font-weight: 600; color: #334155;">
                                        <?php echo htmlspecialchars($req['user_personnel'] ?: ($req['assigned_personnel'] ?: 'Unassigned')); ?>
                                    </div>
                                    <?php if (!empty($req['assigned_team'])): ?>
                                        <div style="font-size: 11px; color: #64748b;">
                                            Team: <?php echo htmlspecialchars($req['assigned_team']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class='badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($req['status']); ?>
                                    </span>
                                    <div class="progress-bar-container" title="<?php echo $progress; ?>% Completed">
                                        <div class="progress-bar-fill" style="width: <?php echo $progress; ?>%;"></div>
                                    </div>
                                    <div style="font-size: 10.5px; color: #64748b; margin-top: 2px;">
                                        <?php echo $progress; ?>% complete
                                    </div>
                                </td>

                                <td style="text-align: right;">
                                    <div class="action-btn-group" style="justify-content: flex-end;">
                                        <a href="maintenance_view.php?id=<?php echo $req['id']; ?>" class="action-btn" title="View Full Record & Diagnostics">
                                            <i class="fai fa-eye"></i>
                                        </a>
                                        <button type="button" class="action-btn btn-update" onclick="openUpdateModal(<?php echo $req['id']; ?>)" title="Update Work Order & Progress">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="action-btn" onclick="openLogsModal(<?php echo $req['id']; ?>, '<?php echo htmlspecialchars($req['request_id']); ?>')" title="View Timeline / Audit Log">
                                            <i class="fas fa-history"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete maintenance record <?php echo $req['request_id']; ?>?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" class="action-btn btn-del" title="Delete Task">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <div style="font-size: 13px; color: #64748b;">
                    Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($totalRecords, $offset + $limit); ?></strong> of <strong><?php echo $totalRecords; ?></strong> records
                </div>
                <div class="pagination-links">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="maintenance_list.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&maintenance_type=<?php echo urlencode($type_filter); ?>&source=<?php echo urlencode($source_filter); ?>&utility_asset_id=<?php echo $asset_filter; ?>" 
                           class="page-link <?php echo $page === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>
<!-- ====================================
     MODAL: CREATE NEW MAINTENANCE TASK
===================================== -->
<div class="modal-backdrop" id="createModal">
    <div class="modal-box modal-box-lg">
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="modal-header">
                <h3><i class="fai fa-plus-circle" style="color: #2563eb;"></i> Register New Maintenance Task</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('createModal')">&times;</button>
            </div>

            <div class="modal-body">
                
                <div class="form-group">
                    <label>Target Utility Asset <small style="font-weight: normal; color: #64748b;">(Select registered asset)</small></label>
                    <select name="utility_asset_id" id="create_asset_id" class="form-select" onchange="autoFillAssetDetails(this)">
                        <option value="">-- No Specific Asset / General Infrastructure --</option>
                        <?php foreach ($assetsList as $ast): ?>
                            <option value="<?php echo $ast['id']; ?>" 
                                    data-location="<?php echo htmlspecialchars($ast['location']); ?>"
                                    data-condition="<?php echo htmlspecialchars($ast['condition_status']); ?>">
                                <?php echo htmlspecialchars($ast['name'] . ' (' . $ast['asset_id'] . ') - ' . $ast['location']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Work Order Title <span class="req">*</span></label>
                        <input type="text" name="title" id="create_title" class="form-control" placeholder="e.g. Pump Valve Replacement, Streetlight Rewiring" required>
                    </div>

                    <div class="form-group">
                        <label>Maintenance Type <span class="req">*</span></label>
                        <select name="maintenance_type" class="form-select" required>
                            <option value="Corrective">Corrective (Repair Broken Issue)</option>
                            <option value="Preventive">Preventive (Scheduled Servicing)</option>
                            <option value="Routine">Routine (Regular Check/Tune-up)</option>
                            <option value="Emergency">Emergency (Immediate Intervention)</option>
                            <option value="Inspection Followup">Inspection Followup</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label>Priority Level <span class="req">*</span></label>
                        <select name="priority" class="form-select" required>
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Source of Request <span class="req">*</span></label>
                        <select name="source" class="form-select" required>
                            <option value="Asset Monitoring" selected>Asset Monitoring</option>
                            <option value="Resident Report">Resident Report</option>
                            <option value="Emergency Alert">Emergency Alert</option>
                            <option value="Scheduled Routine">Scheduled Routine</option>
                            <option value="Inspection">Inspection</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Initial Status</label>
                        <select name="status" class="form-select">
                            <option value="Reported" selected>Reported</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="In Progress">In Progress</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Location / Facility Address <span class="req">*</span></label>
                    <input type="text" name="location" id="create_location" class="form-control" placeholder="Specific street, station, barangay, or coordinates" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Scheduled Start Date</label>
                        <input type="datetime-local" name="scheduled_start_date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Target Completion Date</label>
                        <input type="datetime-local" name="target_completion_date" class="form-control">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Assigned Lead Technician</label>
                        <input type="text" name="assigned_personnel" class="form-control" placeholder="e.g. Engr. Juan Dela Cruz">
                    </div>

                    <div class="form-group">
                        <label>Assigned Department / Crew</label>
                        <input type="text" name="assigned_team" class="form-control" placeholder="e.g. Water Works Team A, Electrical Unit">
                    </div>
                </div>

                <div class="form-group">
                    <label>Issue Description & Scope of Work <span class="req">*</span></label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Provide detailed background, observed anomalies, or specific maintenance tasks needed..." required></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fai fa-save"></i> Create Maintenance Task</button>
            </div>
        </form>
    </div>
</div>

<!-- =====================================
     MODAL: UPDATE WORK ORDER & STATUS
===================================== -->
<div class="modal-backdrop" id="updateModal">
    <div class="modal-box modal-box-lg">
        <form method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="update_id">
            
            <div class="modal-header">
                <h3><i class="fas fa-wrench" style="color: #2563eb;"></i> Update Work Order Progress</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('updateModal')">&times;</button>
            </div>

            <div class="modal-body">
                
                <div style="background: #f1f5f9; padding: 12px 16px; border-radius: 10px; margin-bottom: 18px;">
                    <div style="font-weight: 700; color: #0f172a;" id="update_task_ref">MNT-XXXX</div>
                    <div style="font-size: 13px; color: #475569;" id="update_task_title">Task Title</div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label>Current Status <span class="req">*</span></label>
                        <select name="status" id="update_status" class="form-select" onchange="syncProgressWithStatus(this.value)">
                            <option value="Reported">Reported</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="In Progress">In Progress</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Progress Completion (%) <span class="req">*</span></label>
                        <input type="number" name="progress_percent" id="update_progress_percent" class="form-control" min="0" max="100" value="0">
                    </div>

                    <div class="form-group">
                        <label>Actual Completion Date</label>
                        <input type="datetime-local" name="actual_completion_date" id="update_actual_completion_date" class="form-control">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Scheduled Start Date</label>
                        <input type="datetime-local" name="scheduled_start_date" id="update_scheduled_start_date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Target Completion Date</label>
                        <input type="datetime-local" name="target_completion_date" id="update_target_completion_date" class="form-control">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Assigned Lead Technician</label>
                        <input type="text" name="assigned_personnel" id="update_assigned_personnel" class="form-control" placeholder="Lead technician">
                    </div>

                    <div class="form-group">
                        <label>Assigned Crew / Team</label>
                        <input type="text" name="assigned_team" id="update_assigned_team" class="form-control" placeholder="e.g. Electrical Crew 2">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Technical Findings / Diagnostics</label>
                        <textarea name="findings" id="update_findings" class="form-control" rows="2" placeholder="Describe inspection findings or physical measurements..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Root Cause Analysis</label>
                        <textarea name="root_cause" id="update_root_cause" class="form-control" rows="2" placeholder="Wear and tear, voltage surge, pipe corrosion, clogging, etc."></textarea>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Corrective Action Taken</label>
                        <textarea name="action_taken" id="update_action_taken" class="form-control" rows="2" placeholder="Specific repairs, adjustments, or re-alignments performed..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Parts / Materials Replaced</label>
                        <textarea name="parts_replaced" id="update_parts_replaced" class="form-control" rows="2" placeholder="List valves, cables, breakers, fittings, or seals installed..."></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>Status Update Note / Timeline Log <small>(Required for audit trail)</small></label>
                    <input type="text" name="status_note" class="form-control" placeholder="Brief note on what was accomplished in this update...">
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('updateModal')">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fai fa-check"></i> Save Work Order Updates</button>
            </div>
        </form>
    </div>
</div>

<!-- ====================================
     MODAL: TIMELINE & AUDIT LOGS
==================================== -->
<div class="modal-backdrop" id="logsModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-history" style="color: #2563eb;"></i> Timeline & Audit Trail - <span id="logs_task_ref"></span></h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('logsModal')">&times;</button>
        </div>

        <div class="modal-body">
            <div id="logsLoading" style="text-align: center; padding: 20px; color: #64748b;">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top: 10px;">Loading timeline entries...</p>
            </div>
            
            <div class="timeline-container" id="timelineList" style="display: none;">
                <!-- Filled via JS -->
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('logsModal')">Close</button>
        </div>
    </div>
</div>

<script>
    // Modal Helpers
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function openCreateModal() {
        openModal('createModal');
    }

    function autoFillAssetDetails(selectElem) {
        const selectedOption = selectElem.options[selectElem.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const loc = selectedOption.getAttribute('data-location');
            if (loc) {
                document.getElementById('create_location').value = loc;
            }
        }
    }

    function syncProgressWithStatus(status) {
        const progInput = document.getElementById('update_progress_percent');
        const compDateInput = document.getElementById('update_actual_completion_date');
        
        if (status === 'Completed') {
            progInput.value = 100;
            if (!compDateInput.value) {
                const now = new Date();
                compDateInput.value = now.toISOString().slice(0, 16);
            }
        } elseif (status === 'In Progress' && parseInt(progInput.value) === 0) {
            progInput.value = 25;
        } elseif (status === 'Reported' || status === 'Under Review') {
            if (parseInt(progInput.value) > 20) {
                progInput.value = 0;
            }
        }
    }

    // Open Update Modal and pre-populate via AJAX
    function openUpdateModal(id) {
        fetch(`maintenance_list.php?fetch_record_id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                document.getElementById('update_id').value = data.id;
                document.getElementById('update_task_ref').innerText = `${data.request_id} &bull; ${data.priority} Priority`;
                document.getElementById('update_task_title').innerText = data.title || data.description;
                document.getElementById('update_status').value = data.status || 'Reported';
                document.getElementById('update_progress_percent').value = data.progress_percent || 0;
                document.getElementById('update_assigned_personnel').value = data.assigned_personnel || '';
                document.getElementById('update_assigned_team').value = data.assigned_team || '';
                document.getElementById('update_findings').value = data.findings || '';
                document.getElementById('update_root_cause').value = data.root_cause || '';
                document.getElementById('update_action_taken').value = data.action_taken || '';
                document.getElementById('update_parts_replaced').value = data.parts_replaced || '';

                if (data.scheduled_start_date) {
                    document.getElementById('update_scheduled_start_date').value = data.scheduled_start_date.replace(' ', 'T').slice(0, 16);
                } else {
                    document.getElementById('update_scheduled_start_date').value = '';
                }

                if (data.target_completion_date) {
                    document.getElementById('update_target_completion_date').value = data.target_completion_date.replace(' ', 'T').slice(0, 16);
                } else {
                    document.getElementById('update_target_completion_date').value = '';
                }

                if (data.actual_completion_date) {
                    document.getElementById('update_actual_completion_date').value = data.actual_completion_date.replace(' ', 'T').slice(0, 16);
                } else {
                    document.getElementById('update_actual_completion_date').value = '';
                }

                openModal('updateModal');
            })
            .catch(err => {
                console.error(err);
                alert('Failed to load record details.');
            });
    }

    // Open Logs Timeline Modal via AJAX
    function openLogsModal(id, requestRef) {
        document.getElementById('logs_task_ref').innerText = requestRef;
        document.getElementById('logsLoading').style.display = 'block';
        document.getElementById('timelineList').style.display = 'none';
        document.getElementById('timelineList').innerHTML = '';

        openModal('logsModal');

        fetch(`maintenance_list.php?fetch_logs_id=${id}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('logsLoading').style.display = 'none';
                const list = document.getElementById('timelineList');
                list.style.display = 'block';

                if (!data || data.length === 0) {
                    list.innerHTML = '<p style="color:#94a3b8; text-align:center;">No status history recorded yet.</p>';
                    return;
                }

                data.forEach(item => {
                    const dateObj = new Date(item.changed_at);
                    const formattedDate = dateObj.toLocaleString('en-US', { 
                        month: 'short', day: 'numeric', year: 'numeric', 
                        hour: 'numeric', minute: '2-digit', hour12: true 
                    });

                    const node = document.createElement('div');
                    node.className = 'timeline-item';
                    node.innerHTML = `
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span><i class="fas fa-user-circle"></i> ${item.user_name || 'System'}</span>
                                <span><i class="far fa-clock"></i> ${formattedDate}</span>
                            </div>
                            <div class="timeline-title">
                                ${item.old_status ? `${item.old_status} &rarr; ` : ''} <span style="color:#2563eb;">${item.new_status}</span>
                            </div>
                            <div class="timeline-notes">${item.notes || 'No description provided.'}</div>
                        </div>
                    `;
                    list.appendChild(node);
                });
            })
            .catch(err => {
                document.getElementById('logsLoading').innerHTML = '<p style="color:#ef4444;">Failed to load logs.</p>';
            });
    }

    // Close on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });
</script>

</body>
</html>