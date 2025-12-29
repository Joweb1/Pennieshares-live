<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/email_functions.php';

function getKycStatus($userId) {
    global $pdo_mysql;
    $stmt = $pdo_mysql->prepare("SELECT * FROM kyc_verifications WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function submitKycStep1($userId, $fullName, $dob, $address, $state, $bvn, $nin) {
    global $pdo_mysql;
    $stmt = $pdo_mysql->prepare("SELECT id FROM kyc_verifications WHERE user_id = ?");
    $stmt->execute([$userId]);
    $kycId = $stmt->fetchColumn();

    if ($kycId) {
        $stmt = $pdo_mysql->prepare("UPDATE kyc_verifications SET full_name = ?, dob = ?, address = ?, state = ?, bvn = ?, nin = ?, status = 'pending' WHERE user_id = ?");
        return $stmt->execute([$fullName, $dob, $address, $state, $bvn, $nin, $userId]);
    } else {
        $stmt = $pdo_mysql->prepare("INSERT INTO kyc_verifications (user_id, full_name, dob, address, state, bvn, nin, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        return $stmt->execute([$userId, $fullName, $dob, $address, $state, $bvn, $nin]);
    }
}

function submitKycStep2($userId, $files) {
    global $pdo_mysql;
    $uploadDir = __DIR__ . '/../uploads/kyc/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];

    $paths = [];
    // Handle identity_document
    if (isset($files['identity_document'])) {
        $file = $files['identity_document'];
        if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowedTypes)) {
            $fileName = uniqid() . '-' . basename($file['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $paths['passport_path'] = 'uploads/kyc/' . $fileName; // Using passport_path as generic identity doc
            } else {
                error_log("Failed to move uploaded identity_document file: " . $file['tmp_name'] . " to " . $targetPath);
            }
        } else {
            error_log("Identity_document upload error or invalid type: " . $file['error'] . ", type: " . $file['type']);
        }
    }

    // Handle proof_of_address
    if (isset($files['proof_of_address'])) {
        $file = $files['proof_of_address'];
        if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowedTypes)) {
            $fileName = uniqid() . '-' . basename($file['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $paths['proof_of_address_path'] = 'uploads/kyc/' . $fileName;
            } else {
                error_log("Failed to move uploaded proof_of_address file: " . $file['tmp_name'] . " to " . $targetPath);
            }
        } else {
            error_log("Proof_of_address upload error or invalid type: " . $file['error'] . ", type: " . $file['type']);
        }
    }

    // Handle selfie
    if (isset($files['selfie'])) {
        $file = $files['selfie'];
        if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowedTypes)) {
            $fileName = uniqid() . '-' . basename($file['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $paths['selfie_path'] = 'uploads/kyc/' . $fileName;
            } else {
                error_log("Failed to move uploaded selfie file: " . $file['tmp_name'] . " to " . $targetPath);
            }
        } else {
            error_log("Selfie upload error or invalid type: " . $file['error'] . ", type: " . $file['type']);
        }
    }

    if (!empty($paths)) {
        $sql = "UPDATE kyc_verifications SET ";
        $params = [];
        foreach ($paths as $key => $path) {
            $sql .= "{$key} = ?, ";
            $params[] = $path;
        }
        $sql = rtrim($sql, ', ');
        $sql .= ", status = 'pending' WHERE user_id = ?"; // Set status to pending on new submission
        $params[] = $userId;

        $stmt = $pdo_mysql->prepare($sql);
        $success = $stmt->execute($params);

        if ($success) {
            // Send email to admin
            $stmt = $pdo_mysql->prepare("SELECT username, email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $adminEmail = 'penniepoint@gmail.com'; // Replace with actual admin email
            $subject = 'New KYC Submission';
            $verification_link = 'https://yourdomain.com/admin_kyc'; // Replace with actual link

            $data = [
                'username' => $user['username'],
                'user_email' => $user['email'],
                'submission_date' => date('Y-m-d H:i:s'),
                'verification_link' => $verification_link
            ];

            sendNotificationEmail('kyc_submission_admin', $data, $adminEmail, $subject);
        } else {
            error_log("Database update failed for KYC step 2: " . implode(", ", $stmt->errorInfo()));
        }
        return $success;
    }

    error_log("No valid files to upload for KYC step 2.");
    return false;
}

function getKycSubmissions() {
    global $pdo_mysql;
    $stmt = $pdo_mysql->query("SELECT k.*, u.username, u.email, u.phone FROM kyc_verifications k JOIN users u ON k.user_id = u.id ORDER BY k.created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateKycStatus($kycId, $status) {
    global $pdo_mysql;
    $stmt = $pdo_mysql->prepare("UPDATE kyc_verifications SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $kycId]);
}

function deleteKycVerification($kycId) {
    global $pdo_mysql;
    $stmt = $pdo_mysql->prepare("DELETE FROM kyc_verifications WHERE id = ?");
    return $stmt->execute([$kycId]);
}
?>