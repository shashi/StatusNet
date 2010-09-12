<?php

$dbFile = 'tld.db';

if (file_exists($dbFile)) {
    exit;
}

$db = new PDO('sqlite:' . dirname(__FILE__) . '/' . $dbFile);

$query = '
    CREATE TABLE tld (
        tld VARCHAR(20),
        type VARCHAR(20),
        description VARCHAR(255)
    )
';
$db->exec($query);

$insert = $db->prepare('
    INSERT INTO tld (tld, type, description)
    VALUES (:tld, :type, :description)
');

$contents = file_get_contents(
    'http://www.iana.org/domains/root/db/'
);

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$doc->loadHTML($contents);
libxml_clear_errors();

$descriptions = array(
    'com' => 'Commercial',
    'info' => 'Information',
    'net' => 'Network',
    'org' => 'Organization',
    'edu' => 'Educational',
    'name' => 'Individuals, by name'
);

$xpath = new DOMXPath($doc);
$rows = $xpath->query('//tr[contains(@class, "iana-group")]');
foreach (range(0, $rows->length - 1) as $index) {
    $row = $rows->item($index);
    $tld = strtolower(ltrim($row->childNodes->item(0)->textContent, '.'));
    $type = $row->childNodes->item(1)->nodeValue;
    if (isset($descriptions[$tld])) {
        $description = $descriptions[$tld];
    } else {
        $description = $row->childNodes->item(2)->textContent;
        $regex = '{(^(?:Reserved|Restricted)\s*(?:exclusively\s*)?'
         . '(?:for|to)\s*(?:members of\s*)?(?:the|support)?'
         . '\s*|\s*as advised.*$)}i';
        $description = preg_replace($regex, '', $description);
        $description = ucfirst(trim($description));
    }
    $data = array_map(
        'html_entity_decode',
        array(
            'tld' => $tld,
            'type' => $type,
            'description' => $description
        )
    );
    $insert->execute($data);
}
