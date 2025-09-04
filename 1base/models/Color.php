<?php


/**
 * Representa la tabla 'colors' del catálogo de productos.
 */
interface Color extends InterfaceORM {}; 

class Color_Base extends ORM implements Color 
{
    /** @var string El nombre de la tabla. */
    protected string $tableName = 'colors';
    
    /** @var string El nombre de la clave primaria. */
    protected string $primaryKey = 'id_color';

    /** @var array Lista blanca de columnas para búsquedas. */
    protected array $fillable_columns = ['id_model','id_color']; 
    // Permitimos buscar colores por id_model
}