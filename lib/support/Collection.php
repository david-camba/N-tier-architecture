


<?php
class Collection implements IteratorAggregate, Countable
{
    protected $items = [];
    public function __construct(array $items = []) { $this->items = array_values($items); }

    public function map(callable $callback) { return new self(array_map($callback, $this->items)); }
    public function filter(callable $callback) { return new self(array_filter($this->items, $callback)); }
    public function reduce(callable $callback, $initial = null){ return array_reduce($this->items, $callback, $initial);}

    public function first() { return $this->items[0] ?? null; }
    
    public function toArray()
    {
        return array_map(function ($item) {
            // Si el ítem es un objeto y tiene un método toArray(), llámalo.
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            // Si no (si es un array o cualquier otra cosa), simplemente devuélvelo tal cual.
            return $item;
        }, $this->items);
    }
    
    // Para poder usar la colección en un `foreach`
    public function getIterator(): Traversable { return new ArrayIterator($this->items); }
    // Para poder usar `count($collection)`
    public function count(): int { return count($this->items); }


     /**
     * Obtiene todos los valores para una clave/propiedad dada.
     * Funciona tanto con colecciones de objetos como con colecciones de arrays asociativos.
     *
     * @param string $key La propiedad (para objetos) o la clave (para arrays) a "arrancar".
     * @return array Un array simple con los valores extraídos.
     */
    public function pluck(string $key): array
    {
        return array_map(function ($item) use ($key) {
            // Si el ítem es un objeto...
            if (is_object($item)) {
                // ...accedemos a la propiedad. Esto activará el método mágico __get
                // en tu ORM, lo cual es perfecto.
                return $item->{$key};
            }
            
            // Si es un array y la clave existe...
            if (is_array($item) && array_key_exists($key, $item)) {
                // ...accedemos al valor por su clave.
                return $item[$key];
            }
            
            // Si no es ni un objeto ni un array, o la clave no existe,
            // devolvemos null para evitar errores.
            return null;
        }, $this->items);
    }
}