<?php
require 'config/config.php';
$db = getDB();
$r = $db->query("SELECT id, firstName, surname, matricNumber, faceDescriptor, LENGTH(faceDescriptor) as fd_len FROM students WHERE isActive = 1");
foreach ($r->fetchAll() as $s) {
    echo $s['id'] . ' | ' . $s['firstName'] . ' ' . $s['surname'] . ' | ' . $s['matricNumber'] . ' | fd_len=' . $s['fd_len'] . ' | ' . ($s['fd_len'] > 0 ? 'HAS_DESC' : 'NO_DESC') . PHP_EOL;
}
