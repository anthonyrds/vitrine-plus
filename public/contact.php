<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
  exit;
}

$to = 'vitrineplus@hotmail.com';
$name = trim($_POST['name'] ?? '');
$company = trim($_POST['company'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$budget = trim($_POST['budget'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $company === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
  http_response_code(422);
  echo json_encode(['success' => false, 'message' => 'Informations obligatoires manquantes']);
  exit;
}

$subject = 'Nouvelle demande Vitrine+ — ' . $company;
$body = "Nouvelle demande depuis vitrineplus.fr\n\n"
  . "Nom : $name\nEntreprise : $company\nEmail : $email\nTéléphone : $phone\nBudget : $budget\n\n"
  . "Projet :\n$message\n";

$headers = "From: Vitrine+ <no-reply@vitrineplus.fr>\r\n"
  . "Reply-To: " . $email . "\r\n"
  . "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($to, $subject, $body, $headers);

if (!$sent) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Envoi impossible']);
  exit;
}

echo json_encode(['success' => true]);