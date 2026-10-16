"""Reproducible metadata-only import. No font binary is downloaded by this tool."""
import argparse, hashlib, json, pathlib, re, unicodedata, urllib.request
ROOT = pathlib.Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser()
parser.add_argument('--source-file', type=pathlib.Path)
args = parser.parse_args()
endpoint = 'https://fonts.google.com/metadata/fonts'
raw = args.source_file.read_bytes() if args.source_file else urllib.request.urlopen(endpoint, timeout=30).read(16_000_001)
if len(raw) > 16_000_000: raise ValueError('Font metadata exceeds the import budget')
text = raw.decode('utf-8'); metadata = json.loads(text[text.index('{'):])
rows = {}
category_names = {'Sans Serif':'sans-serif', 'Serif':'serif', 'Monospace':'monospace', 'Display':'display', 'Handwriting':'handwriting'}
for family in metadata['familyMetadataList']:
    if not family.get('isOpenSource', True): continue
    name = family['family']
    slug = re.sub('[^a-z0-9]+', '-', unicodedata.normalize('NFKD', name).encode('ascii', 'ignore').decode().lower()).strip('-')
    identity = 'google:' + slug
    if identity in rows or not slug or len(name) > 160: raise ValueError('Invalid or colliding font identity')
    variants = list(family['fonts'])
    if not variants or any(not re.fullmatch(r'\d{1,4}i?', v) for v in variants): raise ValueError('Invalid font variant')
    axes = {}
    for axis in family['axes']:
        tag = axis['tag']; lo = axis['min']; hi = axis['max']; default = axis['defaultValue']
        if not re.fullmatch(r'[A-Za-z][A-Za-z0-9]{3}', tag) or not all(isinstance(v, (int, float)) and -100000 <= v <= 100000 for v in [lo, hi, default]) or not lo <= default <= hi:
            raise ValueError('Invalid font axis')
        axes[tag] = {'min':lo, 'max':hi, 'default':default}
    rows[identity] = {'family':name,'category':category_names.get(family['category'], 'display'),
        'variants':variants,'subsets':[s for s in family['subsets'] if s != 'menu'],
        'variable':bool(axes),'axes':axes,'rank':family['popularity'],'version':family['lastModified']}
if not 1000 <= len(rows) <= 10000: raise ValueError('Unexpected catalog size')
rows = dict(sorted(rows.items()))
revision = hashlib.sha256(json.dumps(rows, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
snapshot = {'format':1, 'revision':revision, 'fonts':rows}
target = ROOT / 'resources/data/fonts/google.json'
temporary = target.with_suffix('.json.tmp'); temporary.write_text(json.dumps(snapshot, ensure_ascii=False, separators=(',', ':'))+'\n');temporary.replace(target)
(ROOT/'resources/data/fonts/google-source.json').write_text(json.dumps({'source':endpoint,'rawSha256':hashlib.sha256(raw).hexdigest(),'revision':revision,'count':len(rows),'kind':'metadata-only'},indent=2)+'\n')
print(f'Imported {len(rows)} Google font families. Revision {revision}. No font binaries downloaded.')
