import urllib.request, urllib.parse, http.cookiejar, re, json, time, os, pathlib
jar=http.cookiejar.CookieJar();client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
base=os.environ.get('PROTOTYPE_HTTP_URL', 'http://127.0.0.1:8002')
page=client.open(base+'/login').read().decode()
token=re.search(r'name="_csrf_token"[^>]*value="([^"]+)"',page).group(1)
body=urllib.parse.urlencode({'email':os.environ['PROTOTYPE_ADMIN_EMAIL'],'password':os.environ['PROTOTYPE_ADMIN_PASSWORD'],'_csrf_token':token}).encode()
r=client.open(base+'/login',body);print('login',r.status,r.url);r.read()
rows=[]
for path in ['/admin','/admin','/admin/-1/1','/admin/-1/1','/admin/7/1']:
 start=time.perf_counter();r=client.open(base+path);html=r.read().decode();elapsed=(time.perf_counter()-start)*1000
 row={'path':path,'url':r.url,'status':r.status,'elapsed_ms':round(elapsed,2),'profiler_token':r.headers.get('X-Debug-Token'),'has_dashboard':'page-admin-dashboard' in html}
 rows.append(row);print(json.dumps(row))
 if not row['has_dashboard']:raise RuntimeError('Expected authenticated dashboard')
open(pathlib.Path(__file__).resolve().parents[2] / 'var/admin-stats-prototype/http-results.json','w').write(json.dumps(rows,indent=2))
