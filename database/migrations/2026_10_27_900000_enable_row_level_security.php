<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Supabase serves every table in the public schema through its Data API, where
 * the project's anon key could read billing and payments. Row-level security
 * with no policies shuts that API out; the app connects as the table owner, so
 * it is unaffected. An event trigger does the same for tables that later
 * migrations create. Postgres only: SQLite has no such API to close.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE t record;
            BEGIN
                FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
                    EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', t.tablename);
                END LOOP;
            END $$;

            CREATE OR REPLACE FUNCTION public.enable_rls_on_new_tables()
            RETURNS event_trigger LANGUAGE plpgsql AS $$
            DECLARE obj record;
            BEGIN
                FOR obj IN SELECT * FROM pg_event_trigger_ddl_commands()
                    WHERE command_tag = 'CREATE TABLE' AND schema_name = 'public'
                LOOP
                    EXECUTE format('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', obj.object_identity);
                END LOOP;
            END $$;

            DROP EVENT TRIGGER IF EXISTS enable_rls_on_new_tables;
            CREATE EVENT TRIGGER enable_rls_on_new_tables ON ddl_command_end
                WHEN TAG IN ('CREATE TABLE')
                EXECUTE FUNCTION public.enable_rls_on_new_tables();
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP EVENT TRIGGER IF EXISTS enable_rls_on_new_tables;
            DROP FUNCTION IF EXISTS public.enable_rls_on_new_tables();

            DO $$
            DECLARE t record;
            BEGIN
                FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
                    EXECUTE format('ALTER TABLE public.%I DISABLE ROW LEVEL SECURITY', t.tablename);
                END LOOP;
            END $$;
            SQL);
    }
};
