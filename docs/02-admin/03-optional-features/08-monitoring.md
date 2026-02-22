# Internal Mbin Monitoring

We have a few environment variables that can enable monitoring of the mbin server, 
specifically the executed database queries, rendered html components and requested web resources during the execution of
an HTTP request or a message handler (a background job).

Enabling monitoring on your server will have a performance impact. It is not necessarily noticeable for your users, 
but it will increase the resource consumption .  
During an execution context (request or messenger) we will collect monitoring information according to your settings.
After the execution is finished the collected information will be saved to the DB according to your settings
(which is the main performance impact and happens after a request is finished).

The available settings are:
- `MBIN_MONITORING_ENABLED`: Whether monitoring is enabled at all, if `false` then the other settings do not matter
- `MBIN_MONITORING_QUERIES_ENABLED`: Whether to monitor query execution, defaults to true
- `MBIN_MONITORING_QUERY_PERSISTING_ENABLED`: Whether the monitored queries are persisted to the database. If this is disabled only the total query time will be persisted.
- `MBIN_MONITORING_QUERY_PARAMETERS_ENABLED`: Whether the parameter of database queries should be saved. If enabled the spaces used might increase a lot.
- `MBIN_MONITORING_TWIG_RENDERS_ENABLED`: Whether to monitor twig rendering, defaults to true
- `MBIN_MONITORING_TWIG_RENDER_PERSISTING_ENABLED`: Whether to persist the monitored twig renders. If this is disabled only the total rendering time will be persisted.
- `MBIN_MONITORING_CURL_REQUESTS_ENABLED`: Whether to monitor curl requests, defaults to true
- `MBIN_MONITORING_CURL_REQUEST_PERSISTING_ENABLED`: Whether to persist the monitored curl requests. If this is disabled only total request time will be persisted. 

If the monitoring of e.g. queries is enabled, but the persistence is not, 
then the execution context will have a total duration of the executed queries, 
but you cannot inspect the executed queries.

Depending on your persistence settings the monitoring can take up a lot of space. 
The largest amount will come from the queries, then the twig renders and at last the curl requests.

## UI

At `/admin/monitoring` is the overview of the monitoring. There you can see a chart of the longest taking execution contexts.
Underneath that is a table containing the most recent execution contexts according to your filter settings. 
The chart also takes the filter settings into account.

By clicking the alphanumeric string in the first column of the table (part of the GUID of the execution context)
you get to the overview of that context, containing the meta information about this context.

> [!TIP]
> The percentage numbers of "SQL Queries", "Twig Renders" and "Curl Requests" do not necessarily add up to 100% or even below 100%,
> because "Twig Renders" could execute database queries for example.
