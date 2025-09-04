<?php


/**
 * Representa la tabla 'extras' del catálogo de productos.
 */
interface Extra extends InterfaceORM {}; 

class Extra_Base extends ORM implements Extra
{
    /** @var string El nombre de la tabla. */
    protected string $tableName = 'extras';
    
    /** @var string El nombre de la clave primaria. */
    protected string $primaryKey = 'id_extra';

    /** @var array Lista blanca de columnas para búsquedas. */
    protected array $fillable_columns = ['id_extra','name']; // Permitimos buscar extras por nombre
}