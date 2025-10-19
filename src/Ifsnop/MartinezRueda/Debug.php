<?php
namespace Ifsnop\MartinezRueda;

class Debug
{
    public static function on(): bool {
        return defined(Algorithm::class . '::DEBUG') && Algorithm::DEBUG;
    }

    public static function log(string $msg, ...$args): void
    {
        if (!self::on()) return;
        if ($args) {
            $msg = vsprintf($msg, $args);
        }
        // STDERR para separar de la salida funcional
        fwrite(STDERR, "[DBG] " . $msg . PHP_EOL);
    }

    public static function pid(object $o): string
    {
        return '#' . \spl_object_id($o);
    }

    public static function p(Point $p): string
    {
	$px = sprintf("%.12f", self::f($p, 'x'));
	$py = sprintf("%.12f", self::f($p, 'y'));
	if ( false !== ( $pos = strpos($px, ".") ))
	    while ( $px[strlen($px)-1] == '0' )
		$px = substr($px, 0, -1);
	if ( false !== ( $pos = strpos($py, ".") ))
	    while ( $py[strlen($py)-1] == '0' )
		$py = substr($py, 0, -1);
	return sprintf("(%s, %s)", $px, $py);
        // return sprintf("(%.12f, %.12f)", self::f($p, 'x'), self::f($p, 'y'));
    }

    private static function f($obj, string $prop): float
    {
        // Acceso a propiedades privadas mediante closures (solo para debug)
        return (function () use ($prop) { return $this->$prop; })->call($obj);
    }

    public static function segStr(?Segment $s): string
    {
        if ($s === null) return "Segment(NULL)";
        return sprintf(
            "Seg%s %s -> %s | my[%s,%s] oth[%s,%s]",
            self::pid($s),
            self::p($s->start),
            self::p($s->end),
            self::fillBit($s->myFill, 'below'),
            self::fillBit($s->myFill, 'above'),
            self::fillBit($s->otherFill, 'below'),
            self::fillBit($s->otherFill, 'above')
        );
    }

    private static function fillBit(?Fill $f, string $prop): string
    {
        if ($f === null) return '·';
        $v = (function () use ($prop) { return $this->$prop; })->call($f);
        if ($v === null) return '·';
        return $v ? '1' : '0';
    }

    public static function evStr(?Node $ev): string
    {
        if ($ev === null) return "Ev(NULL)";
        return sprintf(
            "Ev%s %s %s -> other(%s) %s",
            self::pid($ev),
            $ev->isStart ? 'START' : 'END  ',
            self::p($ev->pt),
            $ev->other ? self::pid($ev->other) : '·',
            self::segStr($ev->seg)
        );
    }

    public static function dumpEventQueue(LinkedList $root): void
    {
        if (!self::on()) return;
        self::log("— EventQueue BEGIN —");
        $cursor = $root->getHead();
        $i = 0;
        while ($cursor !== null) {
            self::log("  [%02d] %s", $i++, self::evStr($cursor));
            $cursor = $cursor->next;
        }
        self::log("— EventQueue END —");
    }

    public static function dumpStatus(StatusList $root): void
    {
        if (!self::on()) return;
        self::log("— Status BEGIN —");
        $cursor = $root->getHead();
        $i = 0;
        while ($cursor !== null) {
            self::log("  [%02d] St%s -> %s",
                $i++,
                self::pid($cursor),
                self::evStr($cursor->ev)
            );
            $cursor = $cursor->next;
        }
        self::log("— Status END —");
    }
}
