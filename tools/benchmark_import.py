#!/usr/bin/env python3
import argparse,json,mimetypes,subprocess,time,urllib.request,urllib.error
from pathlib import Path

def req(base,path,token=None,method='GET',body=None):
 data=None if body is None else json.dumps(body).encode();h={'Accept':'application/json'}
 if data is not None:h['Content-Type']='application/json'
 if token:h['Authorization']='Bearer '+token
 r=urllib.request.Request(base+path,data=data,headers=h,method=method)
 try:
  with urllib.request.urlopen(r,timeout=60) as x:return json.loads(x.read() or b'{}')
 except urllib.error.HTTPError as e:raise RuntimeError(f'{e.code} {e.read().decode()}')
def curl_upload(base,token,org,path,fmt):
 cmd=['curl','-fsS',base+'/api/imports','-H','Accept: application/json','-H','Authorization: Bearer '+token,'-F',f'organization_id={org}','-F',f'name=benchmark-{Path(path).name}','-F',f'source_type={fmt}','-F',f'file=@{path}']
 return json.loads(subprocess.check_output(cmd))
def wait_import(base,token,id):
 for _ in range(240):
  x=req(base,f'/api/imports/{id}',token);status=x.get('status');status=status.get('value') if isinstance(status,dict) else status
  if status in ('ready','failed'):return x
  time.sleep(.5)
 raise TimeoutError('analysis timed out')
def wait_execution(base,token,imp,eid):
 for _ in range(7200):
  x=req(base,f'/api/imports/{imp}/executions/{eid}',token);st=x['execution']['status'];st=st.get('value') if isinstance(st,dict) else st
  if st in ('completed','completed_with_errors','failed','cancelled'):return x
  time.sleep(1)
 raise TimeoutError('execution timed out')
def main():
 p=argparse.ArgumentParser();p.add_argument('--base-url',default='http://localhost:8000');p.add_argument('--rows',type=int,default=100000);p.add_argument('--format',choices=['csv','ndjson','xlsx'],default='csv');p.add_argument('--chunk-size',type=int,default=2000);p.add_argument('--email',default='benchmark@dataforge.test');p.add_argument('--password',default='benchmark-password');p.add_argument('--organization',default='DataForge Benchmark');a=p.parse_args();base=a.base_url.rstrip('/')
 path=subprocess.check_output(['python','tools/generate_dataset.py','--rows',str(a.rows),'--format',a.format],text=True).strip()
 try:s=req(base,'/api/auth/register',method='POST',body={'name':'Benchmark','email':a.email,'password':a.password,'organization_name':a.organization})
 except RuntimeError:s=req(base,'/api/auth/login',method='POST',body={'email':a.email,'password':a.password})
 token=s['token'];org=(s.get('organization') or s['organizations'][0])['id'];imp=curl_upload(base,token,org,path,a.format);detail=wait_import(base,token,imp['id']);
 if str(detail.get('status')).endswith('failed'):raise RuntimeError('source analysis failed')
 headers=detail['source_schema_json']['headers'];types={h:('integer' if h=='age' else 'email' if h=='email' else 'string') for h in headers};schema=req(base,'/api/schemas',token,'POST',{'organization_id':org,'name':'benchmark-customers','fields':[{'key':h,'type':types[h],'nullable':False if h in ('external_id','email') else True,'constraints':{}} for h in headers]})
 field_by_key={f['key']:f['id'] for f in schema['fields']};mapping={'destination_schema_id':schema['id'],'mappings':[{'destination_field_id':field_by_key[h],'source_column':h,'transforms':[{'type':'trim'}] if types[h]=='string' else []} for h in headers],'validation':{'external_id':[{'type':'required'}],'email':[{'type':'required'},{'type':'email'}]},'dedupe_fields':['external_id'],'duplicate_strategy':'keep_first','db_batch_size':250,'error_source_fields':['external_id','email']};req(base,f"/api/imports/{imp['id']}/mapping",token,'PUT',mapping)
 started=time.time();exe=req(base,f"/api/imports/{imp['id']}/executions",token,'POST',{'chunk_size':a.chunk_size});result=wait_execution(base,token,imp['id'],exe['id']);wall=time.time()-started;out={'rows_requested':a.rows,'format':a.format,'chunk_size':a.chunk_size,'wall_seconds':round(wall,3),'progress':result['progress'],'execution':result['execution'],'chunk_status':result['chunk_status'],'error_distribution':result['error_distribution']};dest=Path(f'storage/benchmarks/result-{a.format}-{a.rows}.json');dest.write_text(json.dumps(out,indent=2,default=str));print(json.dumps(out,indent=2,default=str));print(dest)
if __name__=='__main__':main()
