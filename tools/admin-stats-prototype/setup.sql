-- Local-only performance prototype. Not a production migration.
CREATE SCHEMA admin_perf;
CREATE TABLE admin_perf.actor_state (
 actor_id integer PRIMARY KEY,
 included boolean NOT NULL,
 is_local boolean NOT NULL
);
CREATE TABLE admin_perf.by_actor (
 actor_id integer NOT NULL,
 kind text NOT NULL,
 is_local boolean NOT NULL,
 bucket date NOT NULL,
 cnt bigint NOT NULL CHECK(cnt >= 0),
 PRIMARY KEY(actor_id,kind,is_local,bucket)
);
CREATE TABLE admin_perf.totals (
 shard integer NOT NULL,
 kind text NOT NULL,
 is_local boolean NOT NULL,
 bucket date NOT NULL,
 cnt bigint NOT NULL CHECK(cnt >= 0),
 PRIMARY KEY(shard,kind,is_local,bucket)
);
CREATE TABLE admin_perf.all_time (shard integer NOT NULL,kind text NOT NULL,is_local boolean NOT NULL,cnt bigint NOT NULL CHECK(cnt>=0),PRIMARY KEY(shard,kind,is_local));
CREATE INDEX admin_perf_totals_bucket ON admin_perf.totals(bucket,is_local);
INSERT INTO admin_perf.actor_state VALUES(0,true,false);
INSERT INTO admin_perf.actor_state SELECT id,NOT is_deleted,ap_id IS NULL FROM public."user";
INSERT INTO admin_perf.by_actor SELECT id,'user',ap_id IS NULL,created_at::date,1 FROM public."user";
INSERT INTO admin_perf.by_actor SELECT 0,'magazine',ap_id IS NULL,created_at::date,COUNT(*) FROM magazine GROUP BY 3,4;
DO $$
DECLARE tab text;
BEGIN
 FOREACH tab IN ARRAY ARRAY['entry','entry_comment','post','post_comment'] LOOP
  EXECUTE format('INSERT INTO admin_perf.by_actor SELECT user_id,%L,ap_id IS NULL,created_at::date,COUNT(*) FROM %I GROUP BY 1,3,4',tab,tab);
 END LOOP;
 FOREACH tab IN ARRAY ARRAY['entry_vote','entry_comment_vote','post_vote','post_comment_vote','favourite'] LOOP
  EXECUTE format('INSERT INTO admin_perf.by_actor SELECT e.user_id,%L,u.ap_id IS NULL,e.created_at::date,COUNT(*) FROM %I e JOIN public."user" u ON u.id=e.user_id GROUP BY 1,3,4',tab,tab);
 END LOOP;
END $$;
INSERT INTO admin_perf.totals SELECT MOD(actor_id,64),kind,c.is_local,bucket,SUM(cnt) FROM admin_perf.by_actor c JOIN admin_perf.actor_state s USING(actor_id) WHERE s.included GROUP BY 1,2,3,4;

INSERT INTO admin_perf.all_time SELECT shard,kind,is_local,SUM(cnt) FROM admin_perf.totals GROUP BY 1,2,3;

CREATE FUNCTION admin_perf.day_delta(k text,l boolean,b date,d bigint,s integer) RETURNS void LANGUAGE plpgsql AS $$
BEGIN
 IF d=0 THEN RETURN; END IF;
 IF d<0 THEN
  UPDATE admin_perf.totals SET cnt=cnt+d WHERE shard=s AND kind=k AND is_local=l AND bucket=b;
  IF NOT FOUND THEN RAISE EXCEPTION 'Missing global tally'; END IF;
 ELSE
  INSERT INTO admin_perf.totals(shard,kind,is_local,bucket,cnt) VALUES(s,k,l,b,d)
  ON CONFLICT(shard,kind,is_local,bucket) DO UPDATE SET cnt=admin_perf.totals.cnt+EXCLUDED.cnt;
 END IF;
END $$;

CREATE FUNCTION admin_perf.global_delta(k text,l boolean,b date,d bigint,s integer) RETURNS void LANGUAGE plpgsql AS $$
BEGIN
 IF d=0 THEN RETURN; END IF;
 -- Lock the all-time row before its daily rows to keep day updates ordered.
 IF d<0 THEN
  UPDATE admin_perf.all_time SET cnt=cnt+d WHERE shard=s AND kind=k AND is_local=l;
  IF NOT FOUND THEN RAISE EXCEPTION 'Missing all-time tally'; END IF;
 ELSE
  INSERT INTO admin_perf.all_time(shard,kind,is_local,cnt) VALUES(s,k,l,d)
  ON CONFLICT(shard,kind,is_local) DO UPDATE SET cnt=admin_perf.all_time.cnt+EXCLUDED.cnt;
 END IF;
 PERFORM admin_perf.day_delta(k,l,b,d,s);
END $$;

CREATE FUNCTION admin_perf.delta(a integer,k text,l boolean,m timestamptz,d bigint) RETURNS void LANGUAGE plpgsql AS $$
DECLARE inc boolean;
BEGIN
 SELECT included INTO STRICT inc FROM admin_perf.actor_state WHERE actor_id=a FOR UPDATE;
 IF d<0 THEN
  UPDATE admin_perf.by_actor SET cnt=cnt+d WHERE actor_id=a AND kind=k AND is_local=l AND bucket=m::date;
  IF NOT FOUND THEN RAISE EXCEPTION 'Missing actor tally'; END IF;
 ELSE
  INSERT INTO admin_perf.by_actor(actor_id,kind,is_local,bucket,cnt) VALUES(a,k,l,m::date,d)
  ON CONFLICT(actor_id,kind,is_local,bucket) DO UPDATE SET cnt=admin_perf.by_actor.cnt+EXCLUDED.cnt;
 END IF;
 IF inc THEN PERFORM admin_perf.global_delta(k,l,m::date,d,MOD(a,64)); END IF;
END $$;

CREATE FUNCTION admin_perf.set_included(a integer,inc boolean) RETURNS void LANGUAGE plpgsql AS $$
DECLARE old_inc boolean; r record; sign integer;
BEGIN
 SELECT included INTO STRICT old_inc FROM admin_perf.actor_state WHERE actor_id=a FOR UPDATE;
 IF old_inc=inc THEN RETURN; END IF;
 sign:=CASE WHEN inc THEN 1 ELSE -1 END;
 FOR r IN SELECT * FROM admin_perf.by_actor WHERE actor_id=a ORDER BY kind,is_local,bucket LOOP
  PERFORM admin_perf.global_delta(r.kind,r.is_local,r.bucket,r.cnt*sign,MOD(a,64));
 END LOOP;
 UPDATE admin_perf.actor_state SET included=inc WHERE actor_id=a;
END $$;

CREATE FUNCTION admin_perf.user_event() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE r record; old_local boolean; new_local boolean;
BEGIN
 IF TG_OP='INSERT' THEN
  INSERT INTO admin_perf.actor_state VALUES(NEW.id,NOT NEW.is_deleted,NEW.ap_id IS NULL);
  PERFORM admin_perf.delta(NEW.id,'user',NEW.ap_id IS NULL,NEW.created_at,1);
  RETURN NEW;
 END IF;
 -- This lock serializes a user's tally changes with deletion/locality changes.
 PERFORM 1 FROM admin_perf.actor_state WHERE actor_id=OLD.id FOR UPDATE;
 IF TG_OP='DELETE' THEN
  PERFORM admin_perf.delta(OLD.id,'user',OLD.ap_id IS NULL,OLD.created_at,-1);
  PERFORM admin_perf.set_included(OLD.id,false);
  -- Retain actor_state while cascading child DELETE triggers run.
  RETURN OLD;
 END IF;
 old_local:=OLD.ap_id IS NULL; new_local:=NEW.ap_id IS NULL;
 IF old_local<>new_local OR OLD.created_at<>NEW.created_at THEN
  PERFORM admin_perf.delta(OLD.id,'user',old_local,OLD.created_at,-1);
  PERFORM admin_perf.delta(NEW.id,'user',new_local,NEW.created_at,1);
 END IF;
 PERFORM admin_perf.set_included(NEW.id,NOT NEW.is_deleted);
 IF old_local<>new_local THEN
  FOR r IN SELECT * FROM admin_perf.by_actor WHERE actor_id=NEW.id AND kind IN ('entry_vote','entry_comment_vote','post_vote','post_comment_vote','favourite') AND is_local=old_local AND cnt<>0 ORDER BY kind,bucket LOOP
   PERFORM admin_perf.delta(NEW.id,r.kind,old_local,r.bucket::timestamptz,-r.cnt);
   PERFORM admin_perf.delta(NEW.id,r.kind,new_local,r.bucket::timestamptz,r.cnt);
  END LOOP;
 END IF;
 UPDATE admin_perf.actor_state SET is_local=new_local WHERE actor_id=NEW.id;
 RETURN NEW;
END $$;

CREATE FUNCTION admin_perf.row_delta(k text,j jsonb,d bigint) RETURNS void LANGUAGE plpgsql AS $$
DECLARE a integer; l boolean;
BEGIN
 a:=CASE WHEN k='magazine' THEN 0 ELSE (j->>'user_id')::integer END;
 IF k IN ('entry_vote','entry_comment_vote','post_vote','post_comment_vote','favourite') THEN
  SELECT is_local INTO STRICT l FROM admin_perf.actor_state WHERE actor_id=a FOR UPDATE;
 ELSE
  l:=(j->>'ap_id') IS NULL;
 END IF;
 PERFORM admin_perf.delta(a,k,l,(j->>'created_at')::timestamptz,d);
END $$;

CREATE FUNCTION admin_perf.row_event() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP='UPDATE' THEN
  -- Lock both actors in ID order when moving a record between users.
  PERFORM 1 FROM admin_perf.actor_state WHERE actor_id IN ((to_jsonb(OLD)->>'user_id')::integer,(to_jsonb(NEW)->>'user_id')::integer) ORDER BY actor_id FOR UPDATE;
 END IF;
 IF TG_OP<>'INSERT' THEN PERFORM admin_perf.row_delta(TG_TABLE_NAME,to_jsonb(OLD),-1); END IF;
 IF TG_OP<>'DELETE' THEN PERFORM admin_perf.row_delta(TG_TABLE_NAME,to_jsonb(NEW),1); END IF;
 RETURN COALESCE(NEW,OLD);
END $$;
CREATE TRIGGER admin_perf_user AFTER INSERT OR UPDATE OF is_deleted,ap_id,created_at OR DELETE ON public."user" FOR EACH ROW EXECUTE FUNCTION admin_perf.user_event();
DO $$
DECLARE tab text;
BEGIN
 CREATE TRIGGER admin_perf_event AFTER INSERT OR DELETE OR UPDATE OF ap_id,created_at ON magazine FOR EACH ROW EXECUTE FUNCTION admin_perf.row_event();
 FOREACH tab IN ARRAY ARRAY['entry','entry_comment','post','post_comment'] LOOP
  EXECUTE format('CREATE TRIGGER admin_perf_event AFTER INSERT OR DELETE OR UPDATE OF user_id,ap_id,created_at ON %I FOR EACH ROW EXECUTE FUNCTION admin_perf.row_event()',tab);
 END LOOP;
 FOREACH tab IN ARRAY ARRAY['entry_vote','entry_comment_vote','post_vote','post_comment_vote','favourite'] LOOP
  EXECUTE format('CREATE TRIGGER admin_perf_event AFTER INSERT OR DELETE OR UPDATE OF user_id,created_at ON %I FOR EACH ROW EXECUTE FUNCTION admin_perf.row_event()',tab);
 END LOOP;
 -- The partial boundary day is counted exactly, using a timestamp index.
 FOREACH tab IN ARRAY ARRAY['user','magazine','entry','entry_comment','post','post_comment','entry_vote','entry_comment_vote','post_vote','post_comment_vote','favourite'] LOOP
  EXECUTE format('CREATE INDEX IF NOT EXISTS admin_perf_created_at_%s ON public.%I(created_at)',tab,tab);
 END LOOP;
END $$;
ANALYZE admin_perf.by_actor;
ANALYZE admin_perf.totals;
ANALYZE admin_perf.all_time;
