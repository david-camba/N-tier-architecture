<?php
class Magic{}
class Calculator
{
    public function add($a, $b)
    {
        return $a + $b;
    }

    public function save(int $value1, string $value2, $value3)
    {
        // Supongamos que esto guarda algo en la base de datos
        return "Saved.";
    }

    public function getValue()
    {
        return 42;
    }

    public function doSomething($param)
    {
        return $param * 2;
    }
}