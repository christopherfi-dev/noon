-- Round 4 ruling 4: on a network failure, the curl error number, the stage
-- reached (connect, tls, response) and the elapsed time, as JSON.
ALTER TABLE model_calls ADD COLUMN transport_detail TEXT;
