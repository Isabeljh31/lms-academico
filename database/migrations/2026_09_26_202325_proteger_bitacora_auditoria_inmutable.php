<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ERS RF-99: en PostgreSQL (producción) la base de datos rechaza cualquier
     * UPDATE o DELETE sobre la bitácora de auditoría, aunque se intente fuera
     * de la aplicación. En otros motores (SQLite local y de pruebas) la
     * inmutabilidad la garantiza el modelo BitacoraAuditoria.
     *
     * Nota: las llaves foráneas con ON DELETE SET NULL (usuarios o cursos
     * eliminados) generan un UPDATE interno; como en la aplicación no se
     * eliminan físicamente usuarios ni cursos con historial, se permite solo
     * ese caso para no bloquear el borrado en cascada.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION bitacora_auditoria_inmutable() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'La bitácora de auditoría es inmutable: no se permite eliminar registros.';
    END IF;

    IF NEW.tipo_evento IS DISTINCT FROM OLD.tipo_evento
        OR NEW.valor_anterior IS DISTINCT FROM OLD.valor_anterior
        OR NEW.valor_nuevo IS DISTINCT FROM OLD.valor_nuevo
        OR NEW.motivo IS DISTINCT FROM OLD.motivo
        OR NEW.descripcion IS DISTINCT FROM OLD.descripcion
        OR NEW.created_at IS DISTINCT FROM OLD.created_at
        OR NEW.entidad IS DISTINCT FROM OLD.entidad
        OR NEW.entidad_id IS DISTINCT FROM OLD.entidad_id
        OR NEW.ip IS DISTINCT FROM OLD.ip
        OR (NEW.ejecutado_por IS DISTINCT FROM OLD.ejecutado_por AND NEW.ejecutado_por IS NOT NULL)
        OR (NEW.curso_id IS DISTINCT FROM OLD.curso_id AND NEW.curso_id IS NOT NULL)
        OR (NEW.usuario_afectado_id IS DISTINCT FROM OLD.usuario_afectado_id AND NEW.usuario_afectado_id IS NOT NULL) THEN
        RAISE EXCEPTION 'La bitácora de auditoría es inmutable: no se permite modificar registros.';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS bitacora_auditoria_inmutable ON bitacoras_auditoria;
CREATE TRIGGER bitacora_auditoria_inmutable
    BEFORE UPDATE OR DELETE ON bitacoras_auditoria
    FOR EACH ROW EXECUTE FUNCTION bitacora_auditoria_inmutable();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS bitacora_auditoria_inmutable ON bitacoras_auditoria; DROP FUNCTION IF EXISTS bitacora_auditoria_inmutable();');
    }
};
