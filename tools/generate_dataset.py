#!/usr/bin/env python3
import argparse,csv,json,random,zipfile,itertools
from pathlib import Path
from xml.sax.saxutils import escape

HEADERS=['external_id','first_name','last_name','email','phone','age']
def rows(count,duplicate_rate,invalid_rate,seed):
 random.seed(seed)
 for i in range(count):
  source=max(0,i-1) if i and random.random()<duplicate_rate else i
  email=f'user{source}@example.test'
  if random.random()<invalid_rate: email='not-an-email'
  yield [f'C{source:09d}',f'First{source}',f'Last{source}',email,f'+234800{source%10_000_000:07d}',18+source%60]
def csv_write(path,data):
 with path.open('w',newline='',encoding='utf-8') as f:
  w=csv.writer(f);w.writerow(HEADERS);w.writerows(data)
def ndjson_write(path,data):
 with path.open('w',encoding='utf-8') as f:
  for row in data:f.write(json.dumps(dict(zip(HEADERS,row)),separators=(',',':'))+'\n')
def xlsx_write(path,data):
 # Minimal standards-compliant XLSX using inline strings, generated streaming to a temp XML file.
 sheet=path.with_suffix('.sheet.xml')
 with sheet.open('w',encoding='utf-8') as f:
  f.write('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>')
  for rn,row in enumerate(itertools.chain([HEADERS],data),1):
   f.write(f'<row r="{rn}">')
   for ci,v in enumerate(row):
    n=ci+1;letters=''
    while n:n,rem=divmod(n-1,26);letters=chr(65+rem)+letters
    if isinstance(v,(int,float)):f.write(f'<c r="{letters}{rn}"><v>{v}</v></c>')
    else:f.write(f'<c r="{letters}{rn}" t="inlineStr"><is><t>{escape(str(v))}</t></is></c>')
   f.write('</row>')
  f.write('</sheetData></worksheet>')
 with zipfile.ZipFile(path,'w',zipfile.ZIP_DEFLATED) as z:
  z.writestr('[Content_Types].xml','''<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>''')
  z.writestr('_rels/.rels','''<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>''')
  z.writestr('xl/workbook.xml','''<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Customers" sheetId="1" r:id="rId1"/></sheets></workbook>''')
  z.writestr('xl/_rels/workbook.xml.rels','''<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>''')
  z.write(sheet,'xl/worksheets/sheet1.xml')
 sheet.unlink()

def main():
 p=argparse.ArgumentParser();p.add_argument('--rows',type=int,default=100_000);p.add_argument('--format',choices=['csv','ndjson','xlsx'],default='csv');p.add_argument('--out');p.add_argument('--duplicate-rate',type=float,default=.03);p.add_argument('--invalid-rate',type=float,default=.002);p.add_argument('--seed',type=int,default=42);a=p.parse_args();out=Path(a.out or f'storage/benchmarks/customers-{a.rows}.{a.format}');out.parent.mkdir(parents=True,exist_ok=True);data=list(rows(a.rows,a.duplicate_rate,a.invalid_rate,a.seed)) if a.format=='xlsx' else rows(a.rows,a.duplicate_rate,a.invalid_rate,a.seed);{'csv':csv_write,'ndjson':ndjson_write,'xlsx':xlsx_write}[a.format](out,data);print(out)
if __name__=='__main__':main()
