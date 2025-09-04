<?php
// EN: 3audi/models/Model.php



/**
 * Representa la tabla 'models' del catálogo de productos.
 * Este modelo es específico de la capa Audi.
 */
interface CarModel extends InterfaceORM {}; 

class CarModel_Base extends ORM implements CarModel
{
    /** @var string El nombre de la tabla en la base de datos. */
    protected string $tableName = 'models';
    
    /** @var string El nombre de la clave primaria. */
    protected string $primaryKey = 'id_model';

    /** @var array Lista blanca de columnas para búsquedas. */
    protected array $fillable_columns = ['id_model','name']; // Permitimos buscar modelos por nombre

    protected function colors(){
        return $this->hasMany("Color");
    }

    protected function extras(){
        return $this->belongsToMany("Extra","extra_model");
    }
}