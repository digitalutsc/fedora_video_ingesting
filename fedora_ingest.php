#!/usr/bin/php
<?php
/**
 * Created by PhpStorm.
 * User: Lingling Jiang
 *
 * This script is used to create and ingest large video files to Fedora Repo directly without islandora.
 *
 * Example usage: php fedora_ingest.php demo /absolute/path/to/ingest/directory
 *  the collection ingested will be demo:collection when collection argument is not specified in the command.
 *
 * Example usage: php fedora_ingest.php demo /absolute/path/to/ingest/directory email_address_to_notify my_new_collection
 */
//error_reporting(E_ALL);
//ini_set('display_errors', '1');

if (PHP_SAPI === 'cli') {
  $namespace = isset($argv[1]) ? trim($argv[1]) : null;
  // It requires an absolute path
  $ingest_dir = isset($argv[2]) ? trim($argv[2]) : null;
  $email = isset($argv[3]) ? trim($argv[3]) : null;
  $collection = isset($argv[4]) ? trim($argv[4]) : $namespace . ':collection';
}
else {
  $namespace = isset($_GET['ns']) ? trim($_GET['ns']) : null;
  $ingest_dir = isset($_GET['dir']) ? trim($_GET['dir']) : null;
  $collection = isset($_GET['col']) ? trim($_GET['col']) : $namespace . ':collection';
  $email = isset($_GET['email']) ? trim($_GET['email']) : null;
}

if (empty($namespace) || empty($ingest_dir)) {
  echo "Namespace and ingest directory are required! Please execute the script again!<br>\r\n";
  echo "Example: php fedora_ingest.php demo /absolute/path/to/ingest/directory<br> \r\n";
  exit;
}

/**
 * Configuration
 */
$base_url = 'http://dsu-fedora.utsc.utoronto.ca:8080';
$username = 'fedoraAdmin';
$pass = 'fcw0wSuper';

$model = 'islandora:sp_videoCModel';

$log_file = 'ingest_' . date('Y_m_d') . '.log';
$log_msg = date('Y-m-d H:i:s') . " Ingest job starting...\r\n";


// First to get a new PID in the namespace

$url = $base_url . '/fedora/objects/nextPID?namespace=' . $namespace . '&format=xml';
$response = run_curl($url, $username, $pass, NULL, '');
if ($response['httpcode'] == 200) {
  $resXML = new SimpleXMLElement($response['result']);
  $pid = $resXML->pid;
}
else {
  $log_msg .= date('Y-m-d H:i:s') . " Failed to get new PID ...\r\n";
  echo "Failed to get a new PID ... exit the script!<br>\r\n";
  exit;
}

// Create a new object with the PID
$url = $base_url . '/fedora/objects/new?ignoreMime=true';

$xml = 'objxml.xml';

$dom = new DOMDocument();
$dom->preserveWhiteSpace = FALSE;
$dom->formatOutput = true;
$dom->load($xml);

// update obj xml with new PID

$foxmlObject = $dom->getElementsByTagNameNS('info:fedora/fedora-system:def/foxml#', 'digitalObject')->item(0);
$foxmlObject->setAttribute('PID', $pid);

$foxmlProperty = $dom->getElementsByTagNameNS('info:fedora/fedora-system:def/foxml#', 'property');
foreach ( $foxmlProperty as $property) {
  if (trim($property->getAttribute('NAME') == 'info:fedora/fedora-system:def/model#label')) {
    $property->setAttribute('VALUE', $pid);
  }
}


$dcTitle = $dom->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'title')->item(0);
$dcTitle->nodeValue = $pid;

$dcIdentifier = $dom->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'identifier')->item(0);
$dcIdentifier->nodeValue = $pid;

$rdfDesc = $dom->getElementsByTagNameNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'Description')->item(0);
$rdfDesc->setAttributeNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:about', 'info:fedora/' . $pid);

$fedoraMember = $dom->getElementsByTagNameNS('info:fedora/fedora-system:def/relations-external#', 'isMemberOfCollection')->item(0);
$fedoraMember->setAttributeNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:resource', 'info:fedora/' . $collection);

$fedoraModel = $dom->getElementsByTagNameNS('info:fedora/fedora-system:def/model#', 'hasModel')->item(0);
$fedoraModel->setAttributeNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:resource', 'info:fedora/' . $model);

$headers = array(
  "Content-type: text/xml",
);

$response = run_curl($url, $username, $pass, $headers, $dom->saveXML());

if ($response['httpcode'] == 201) {
  $log_msg .= date('Y-m-d H:i:s') . " " . $pid . " is created and ingested successfully\r\n";
  $pid = $resXML->pid;
}
else {
  $log_msg .= date('Y-m-d H:i:s') . " Failed to create new object " . $pid . " ... exit the script!\r\n";
  echo "Failed to create object " . $pid . " ... exit the script!<br>\r\n";
  exit;
}

// Scan Ingest Dir for .mov file, .mp4 file, MODS.xml, TN.jpg to add datastreams.

$files = new FilesystemIterator($ingest_dir);
$files->setFlags(FilesystemIterator::UNIX_PATHS);
foreach ($files as $file) {
  switch (strtolower($file->getExtension())) {
    case 'mov':
      $url = $base_url . '/fedora/objects/' . $pid . '/datastreams/OBJ?controlGroup=M&dsLabel=OBJ&mimeType=video/quicktime';
      if (function_exists('curl_file_create')) { // PHP 5.5+
        $request = array(
          'file' => curl_file_create($file->getPathname(), 'video/quicktime', $file->getFilename())
        );
      }
      else {
        $request = array(
          'file' => '@' . $file->getPathname()
        );
      }

      $response = run_curl($url, $username, $pass, NULL, $request);
      if ($response['httpcode'] == 201) {
        $log_msg .= date('Y-m-d H:i:s') . " " . $pid. " OBJ datastream is created and ingested successfully\r\n";
        $pid = $resXML->pid;
      }
      else {
        $log_msg .= date('Y-m-d H:i:s') . " " . $pid. " OBJ datastream failed\r\n";
        echo "Failed to create OBJ datastream for " . $pid . " ... exit the script!<br>\r\n";
        exit;
      }
      break;

    case 'mp4':
      $url = $base_url . '/fedora/objects/' . $pid . '/datastreams/MP4?controlGroup=M&dsLabel=MP4&mimeType=video/mp4';
      if (function_exists('curl_file_create')) { // PHP 5.5+
        $request = array(
          'file' => curl_file_create($file->getPathname(), 'video/mp4', $file->getFilename())
        );
      }
      else {
        $request = array(
          'file' => '@' . $file->getPathname()
        );
      }

      $response = run_curl($url, $username, $pass, NULL, $request);
      if ($response['httpcode'] == 201) {
        $log_msg .= date('Y-m-d H:i:s') . " " . $pid. " MP4 datastream is created and ingested successfully\r\n";
        $pid = $resXML->pid;
      }
      else {
        $log_msg .= date('Y-m-d H:i:s') . " " . $pid. " MP4 datastream failed\r\n";
        echo "Failed to create MP4 datastream for " . $pid . " ... exit the script!<br>\r\n";
        exit;
      }
      break;

    case 'xml':
      $url = $base_url . '/fedora/objects/' . $pid . '/datastreams/MODS?controlGroup=M&dsLabel=MODS&mimeType=text/xml';
      if (function_exists('curl_file_create')) { // PHP 5.5+
        $request = array(
          'file' => curl_file_create($file->getPathname(), 'text/xml', $file->getFilename())
        );
      }
      else {
        $request = array(
          'file' => '@' . $file->getPathname()
        );
      }

      $response = run_curl($url, $username, $pass, NULL, $request);
      if ($response['httpcode'] == 201) {
        $log_msg .= date('Y-m-d H:i:s') . " " . $pid. " MODS datastream is created and ingested successfully\r\n";
        $pid = $resXML->pid;
      }
      else {
        $log_msg .= date('Y-m-d H:i:s') . " " . $pid. " MODs datastream failed\r\n";
        echo "Failed to create MODS datastream for " . $pid . " ... exit the script!<br>\r\n";
        exit;
      }
      break;

    case 'jpg':
      $url = $base_url . '/fedora/objects/' . $pid . '/datastreams/TN?controlGroup=M&dsLabel=TN&mimeType=image/jpeg';
      if (function_exists('curl_file_create')) { // PHP 5.5+
        $request = array(
          'file' => curl_file_create($file->getPathname(), 'image/jpeg', $file->getFilename())
        );
      }
      else {
        $request = array(
          'file' => '@' . $file->getPathname()
        );
      }

      $response = run_curl($url, $username, $pass, NULL, $request);
      if ($response['httpcode'] == 201) {
        $log_msg .= date('Y-m-d H:i:s') . " " . $pid. " TN datastream is created and ingested successfully\r\n";
        $pid = $resXML->pid;
      }
      else {
        $log_msg .= date('Y-m-d H:i:s') . " " . $pid. " TN datastream failed\r\n";
        echo "Failed to create TN datastream for " . $pid . " ... exit the script!<br>\r\n";
        exit;
      }
      break;

    default:
      $log_msg .= date('Y-m-d H:i:s') . " " . $file->getFilename() . " is not a .mov file, .mp4 file, MODS.xml or TN.jpg. Exit the script ..\r\n";
      echo $file->getFilename() . ' is not a .mov file, .mp4 file, MODS.xml or TN.jpg. Exit the script ...';
      exit;
      break;
  }
}

// Log message
$log_msg .= date('Y-m-d H:i:s') . " " . $pid . " Ingest job is done\r\n";
file_put_contents($log_file, $log_msg, FILE_APPEND | LOCK_EX);

if (!empty($email)) {
  $subject = $pid . ' is ingested successfully at ' . date('Y-m-d H:i:s');
  mail_admin($subject, $email, $log_msg);
}


/**
 * Helper function to execute a curl call.
 *
 * @param $url
 * @param null $username
 * @param null $password
 * @param $headers
 * @param $request
 * @return array
 */
function run_curl($url, $username = NULL, $password = NULL , $headers, $request) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  if (!empty($username) && !empty($password)) {
    curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password); // username and password
  }
  curl_setopt($ch, CURLOPT_POST, TRUE);
  if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  }
  curl_setopt($ch, CURLOPT_POSTFIELDS, $request);


  $response = curl_exec($ch);
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return array(
    'result' => $response,
    'httpcode' => $httpcode
  );
}


/**
 * Helper function to send email after the job is done.
 *
 * @param $sub
 * @param $to
 * @param string $msg
 */
function mail_admin($sub, $to, $msg = '') {
  $headers = 'From: dsu-fedora@utsc.utoronto.ca' . "\r\n" .
    'Reply-To: dsu-fedora@utsc.utoronto.ca' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

  mail($to, $sub, $msg, $headers);
}