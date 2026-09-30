<?php
require 'db.php';

$ref = new ReflectionFunction('localTime');
echo '<pre>';
echo "localTime is defined in:\n";
echo $ref->getFileName() . "\n";
echo "Line: " . $ref->getStartLine() . "\n";
echo "\nParameters:\n";
foreach ($ref->getParameters() as $p) {
    echo "- $" . $p->getName();
    if ($p->hasType()) {
        echo " (type: " . $p->getType() . ")";
    }
    echo "\n";
}
echo '</pre>';