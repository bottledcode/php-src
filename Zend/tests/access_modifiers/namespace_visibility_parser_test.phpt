--TEST--
Parser can distinguish namespace declaration from namespace visibility modifier
--FILE--
<?php

// Test 1: namespace declaration followed by namespace visibility
namespace App\Auth;

class SessionManager {
    namespace function helper(): void {
        echo "Helper called\n";
    }

    public namespace(set) int $count = 0;
}

// Test 2: Call from same namespace
$session = new SessionManager();
$session->helper();
$session->count++;
echo "Count: {$session->count}\n";

// Test 3: Different namespace
namespace App\Other;

$session2 = new \App\Auth\SessionManager();
try {
    $session2->helper();
    echo "ERROR: Should have thrown\n";
} catch (\Error $e) {
    echo "Correctly blocked: namespace visibility works\n";
}

?>
--EXPECT--
Helper called
Count: 1
Correctly blocked: namespace visibility works
