--TEST--
Monomorph unserialize: a fresh process can unserialize a canonical generic name even when no instance has been constructed yet
--FILE--
<?php
// Repro for cross-process unserialize: serialize() emits the canonical
// monomorph name (e.g. Box<int>); unserialize() in a fresh process must
// be able to materialize that monomorph just from the name, without the
// caller priming it by constructing an instance first.

class Box<T = mixed> {
    public function __construct(public T $value) {}
}

// Produce the canonical payload from a primed process.
$payload = serialize(new Box::<int>(42));

$tmp = tempnam(sys_get_temp_dir(), 'monomorph_unserialize_');
file_put_contents($tmp, $payload);

// Run the unserialize side in a fresh PHP process. The child declares the
// generic template but never constructs an instance, so the monomorph
// Box<int> only exists as a name in the serialized payload.
$script = <<<'PHP'
<?php
class Box<T = mixed> {
    public function __construct(public T $value) {}
}
$s = file_get_contents($argv[1]);
$b = unserialize($s);
$out = "###RESULT###\n";
$out .= var_export($b instanceof Box, true) . "\n";
$out .= var_export($b::class, true) . "\n";
$out .= var_export($b->value, true) . "\n";
file_put_contents($argv[2], $out);
PHP;

$scriptFile = tempnam(sys_get_temp_dir(), 'monomorph_unserialize_script_');
file_put_contents($scriptFile, $script);

$resultFile = tempnam(sys_get_temp_dir(), 'monomorph_unserialize_result_');

$cmd = sprintf(
    '%s -n %s %s %s >/dev/null 2>&1',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($scriptFile),
    escapeshellarg($tmp),
    escapeshellarg($resultFile),
);
shell_exec($cmd);

echo $payload, "\n";
echo file_get_contents($resultFile);

@unlink($tmp);
@unlink($scriptFile);
@unlink($resultFile);
?>
--EXPECT--
O:8:"Box<int>":1:{s:5:"value";i:42;}
###RESULT###
true
'Box<int>'
42
