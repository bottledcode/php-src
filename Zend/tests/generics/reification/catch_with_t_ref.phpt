--TEST--
Reification: catch (Box<T> $e) matches only when the thrown exception's monomorph args agree with T
--FILE--
<?php
class BoxedError<T> extends Exception {}

function tryCatch<T>(Exception $e): string {
    try {
        throw $e;
    } catch (BoxedError<T> $caught) {
        return "matched BoxedError<T>";
    } catch (Exception $other) {
        return "fell through";
    }
}

echo tryCatch::<int>(new BoxedError::<int>('payload')), "\n";    // matched
echo tryCatch::<int>(new BoxedError::<string>('payload')), "\n"; // fell through: different mono
echo tryCatch::<int>(new Exception('plain')), "\n";              // fell through: not a Box*
echo tryCatch::<string>(new BoxedError::<string>('s')), "\n";    // matched
?>
--EXPECT--
matched BoxedError<T>
fell through
fell through
matched BoxedError<T>
