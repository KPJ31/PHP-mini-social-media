"""HTTP regression tests using an isolated temporary MySQL 8 database and app copy."""
import argparse, base64, http.cookiejar, json, os, re, shutil, socket, subprocess, tempfile, time
from pathlib import Path
from urllib.request import Request, build_opener, HTTPCookieProcessor
from urllib.error import HTTPError
from urllib.parse import urlencode

ROOT = Path(__file__).resolve().parents[1]
def port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]
def wait_port(value, process):
    deadline = time.monotonic() + 60
    while time.monotonic() < deadline:
        if process.poll() is not None:
            raise RuntimeError('Server exited early')
        try:
            with socket.create_connection(('127.0.0.1', value), .2): return
        except OSError: time.sleep(.2)
    raise RuntimeError('Server did not start')

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--mysql-bin', required=True)
    parser.add_argument('--ui', action='store_true')
    args = parser.parse_args()
    mysql_bin = Path(args.mysql_bin)
    suffix = '.exe' if os.name == 'nt' else ''
    flags = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0
    processes = []
    checks = 0
    artifacts = ROOT / 'tests' / 'artifacts' / 'audit'
    artifacts.mkdir(parents=True, exist_ok=True)
    passed = []
    with tempfile.TemporaryDirectory(prefix='minisocial-qa-') as directory:
        temp = Path(directory)
        app = temp / 'app'
        app.mkdir()
        for pattern in ['*.php', '*.js']:
            for file in ROOT.glob(pattern): shutil.copy2(file, app / file.name)
        for folder in ['js', 'css']: shutil.copytree(ROOT / folder, app / folder)
        (app / 'uploads').mkdir()
        shutil.copy2(ROOT / 'uploads' / 'default.svg', app / 'uploads' / 'default.svg')
        shutil.copy2(ROOT / 'likePost.js', app / 'likePost.js')
        data = temp / 'mysql-data'
        db_port, http_port = port(), port()
        log = open(temp / 'servers.log', 'w+', encoding='utf-8')
        def sql(query, database=True):
            command = [str(mysql_bin / ('mysql'+suffix)), '--no-defaults', '--host=127.0.0.1',
                       '--port='+str(db_port), '--user=root', '--batch', '--skip-column-names',
                       '--default-character-set=utf8mb4']
            if database: command += ['mini_social_network']
            result = subprocess.run(command, input=query, text=True, encoding='utf-8', capture_output=True, creationflags=flags)
            if result.returncode: raise AssertionError(result.stderr)
            return result.stdout.strip()
        def check(condition, label):
            nonlocal checks
            assert condition, label
            checks += 1
            passed.append(label)
            print('PASS: '+label, flush=True)
        try:
            init = subprocess.run([str(mysql_bin / ('mysqld'+suffix)), '--no-defaults',
                '--initialize-insecure', '--datadir='+str(data)], stdout=log, stderr=log, creationflags=flags)
            if init.returncode: raise RuntimeError('MySQL initialization failed')
            db = subprocess.Popen([str(mysql_bin / ('mysqld'+suffix)), '--no-defaults',
                '--datadir='+str(data), '--port='+str(db_port), '--bind-address=127.0.0.1',
                '--mysqlx=0'], stdout=log, stderr=log, creationflags=flags)
            processes.append(db)
            wait_port(db_port, db)
            sql((ROOT/'mini_social_network.sql').read_text(), False)
            env = os.environ.copy()
            env.update(DB_HOST='127.0.0.1', DB_PORT=str(db_port), DB_USER='root', DB_PASSWORD='', DB_NAME='mini_social_network')
            web = subprocess.Popen(['php','-d','display_errors=1','-d','error_reporting=32767',
                                    '-S','127.0.0.1:'+str(http_port),'-t',str(app)],
                                   env=env, stdout=log, stderr=log, creationflags=flags)
            processes.append(web)
            wait_port(http_port, web)
            base = 'http://127.0.0.1:'+str(http_port)+'/'
            class Client:
                def __init__(self):
                    self.opener = build_opener(HTTPCookieProcessor(http.cookiejar.CookieJar()))
                    self.token = ''
                def request(self, path, fields=None, ajax=False, raw=None, content_type=None, token=True):
                    headers = {'X-Requested-With':'XMLHttpRequest'} if ajax else {}
                    body = None
                    if fields is not None:
                        fields = dict(fields)
                        if token: fields['csrf_token'] = self.token
                        body = urlencode(fields).encode()
                    if raw is not None:
                        body = raw
                        headers['Content-Type'] = content_type
                    request = Request(base+path, data=body, headers=headers)
                    try: response = self.opener.open(request, timeout=10)
                    except HTTPError as e: response = e
                    text = response.read().decode('utf-8')
                    assert not re.search(r'(Fatal error|Warning:|Deprecated:|Parse error)', text), text
                    match = re.search(r'name="csrf-token" content="([^"]+)"', text)
                    if match: self.token = match[1]
                    return response.status, text
                def register(self, name):
                    self.request('register.php')
                    return self.request('register.php', dict(username=name,email=name+'@example.test',password='Test-password-123',confirm_password='Test-password-123'))
                def upload(self, path, fields, name, filename, data):
                    boundary = 'MiniSocialTestBoundary'
                    fields = dict(fields, csrf_token=self.token)
                    chunks = []
                    for key,value in fields.items():
                        chunks.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+key+'"\r\n\r\n'+value+'\r\n').encode())
                    chunks += [('--'+boundary+'\r\nContent-Disposition: form-data; name="'+name+'"; filename="'+filename+'"\r\nContent-Type: application/octet-stream\r\n\r\n').encode(), data, ('\r\n--'+boundary+'--\r\n').encode()]
                    return self.request(path, raw=b''.join(chunks),content_type='multipart/form-data; boundary='+boundary)
            alice,bob,eve = Client(),Client(),Client()
            for client,name in [(alice,'alice'),(bob,'bob'),(eve,'eve')]:
                check(client.register(name)[0] == 200, 'register '+name)
            check(alice.request('add_post.php',{'content':'blocked'},token=False)[0] == 403,'CSRF rejected')
            for path in ['delete_post.php','delete_comment.php','delete_message.php','send_request.php','accept_request.php','send_message.php','delete_account.php','logout.php']:
                check(alice.request(path)[0] == 405,'GET rejected: '+path)
            check(alice.request('profile.php?user_id=999')[0] == 404,'missing profile')
            check(alice.request('comment_post.php?post_id=999')[0] == 404,'missing post')
            check(alice.request('comment_post.php?post_id=999',{'comment':'test'})[0] == 404,'comment on missing post')
            check(alice.request('add_post.php',{'content':'Hello <script>bad()</script>'})[0] == 200,'create post')
            check('&lt;script&gt;' in bob.request('index.php')[1],'post escaped')
            check(json.loads(bob.request('like_post.php',{'post_id':'1'})[1])['likes'] == 1,'like post')
            check(json.loads(bob.request('like_post.php',{'post_id':'1'})[1])['likes'] == 0,'unlike post')
            check(bob.request('like_post.php',{'post_id':'999'})[0] == 404,'like missing post')
            check(bob.request('comment_post.php',{'post_id':'1','comment':'A comment'},True)[1] == 'success','AJAX comment')
            check('A comment' in alice.request('get_comments.php?post_id=1')[1],'load comments with valid timestamp')
            check(bob.request('comment_post.php?post_id=1',{'comment':'0'})[0] == 200,'zero comment retained')
            check(sql('SELECT COUNT(*) FROM comments') == '2','both comments saved')
            eve.request('delete_comment.php',{'id':'1','post_id':'1'})
            check(sql('SELECT COUNT(*) FROM comments') == '2','unauthorized comment deletion blocked')
            alice.request('delete_comment.php',{'id':'1','post_id':'1'})
            check(sql('SELECT COUNT(*) FROM comments') == '1','post owner deletes comment')
            check(alice.request('send_message.php',{'receiver_id':'2','message':'No'},True)[0] == 403,'nonfriend chat blocked')
            alice.request('send_request.php',{'user_id':'2'})
            alice.request('send_request.php',{'user_id':'2'})
            bob.request('send_request.php',{'user_id':'1'})
            check(sql('SELECT COUNT(*) FROM friend_requests') == '1','duplicate and reciprocal friend requests prevented')
            check(alice.request('chat.php?user_id=2')[0] == 403,'pending friendship cannot chat')
            eve.request('accept_request.php',{'user_id':'1'})
            check(sql('SELECT status FROM friend_requests') == 'pending','third party cannot accept request')
            bob.request('accept_request.php',{'user_id':'1'})
            check(sql('SELECT status FROM friend_requests') == 'accepted','accept friendship')
            check(alice.request('send_message.php',{'receiver_id':'2','message':'Hello Bob'},True)[0] == 204,'send friend message')
            check('Hello Bob' in bob.request('load_chat_messages.php?receiver_id=1')[1],'receive message')
            check(sql('SELECT is_read FROM messages WHERE id=1') == '1','mark message read')
            check(eve.request('delete_message.php',{'id':'1'})[0] == 404,'third party cannot delete message')
            check(alice.request('delete_message.php',{'id':'999'})[0] == 404,'missing message safe')
            alice.request('delete_message.php',{'id':'1'})
            for path in ['chat.php?user_id=2','get_messages.php?user_id=2','load_chat_messages.php?receiver_id=2']:
                check('Hello Bob' not in alice.request(path)[1],'sender deletion respected: '+path)
            check('Hello Bob' in bob.request('chat.php?user_id=1')[1],'recipient retains message')
            check('Email or username already taken.' in alice.request('edit_profile.php',dict(update_profile='1',username='bob',email='alice@example.test',bio=''))[1],'duplicate profile handled')
            check(alice.request('edit_profile.php',dict(update_profile='1',username='alice2',email='alice2@example.test',bio='Updated'))[0] == 200,'profile updated')
            check('valid JPG' in alice.upload('add_post.php',{'content':'invalid image'},'image','fake.jpg',b'<?php echo "bad"; ?>')[1],'fake image rejected')
            png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
            check(alice.upload('edit_profile.php',dict(update_profile='1',username='alice2',email='alice2@example.test',bio='Updated'),'profile_image','avatar.php',png)[0] == 200,'valid image uploaded with canonical extension')
            check(sql('SELECT profile_image FROM users WHERE id=1').endswith('.png'),'untrusted upload extension replaced')
            # Validate compatibility with the original likes schema without foreign keys.
            for constraint in sql("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='mini_social_network' AND TABLE_NAME='likes' AND REFERENCED_TABLE_NAME IS NOT NULL").splitlines():
                sql('ALTER TABLE likes DROP FOREIGN KEY '+constraint)
            bob.request('like_post.php',{'post_id':'1'})
            bob.request('add_post.php',{'content':'Bob post'})
            alice.request('like_post.php',{'post_id':'2'})
            bob.request('delete_post.php',{'post_id':'1'})
            check(sql('SELECT COUNT(*) FROM posts WHERE id=1') == '1','post ownership enforced')
            alice.request('delete_account.php',{'delete_account':'1'})
            check(sql('SELECT COUNT(*) FROM likes') == '0','account deletion cleans own and received likes on legacy schema')
            check(sql('SELECT COUNT(*) FROM posts WHERE user_id=1') == '0','account posts cascade')
            bob.request('delete_post.php',{'post_id':'2'})
            check(sql('SELECT COUNT(*) FROM posts') == '0','post deletion')
            bob.request('logout.php',{})
            check('Login - Mini' in bob.request('profile.php')[1],'logout clears authentication')
            check(bob.request('login.php',dict(email='bob@example.test',password='Test-password-123'))[0] == 200,'login')
            for path in ['index.php','profile.php','edit_profile.php','friend_list.php','chat.php','add_post.php']:
                check(bob.request(path)[0] == 200,'page renders: '+path)
            check(bob.request('send_message.php',{'receiver_id':'3','message':'0'},True)[0] == 403,'zero message still needs friendship')
            bob.request('send_request.php', {'user_id':'3'})
            eve.request('accept_request.php', {'user_id':'2'})
            check(bob.request('send_message.php',{'receiver_id':'3','message':'0'},True)[0] == 204,'zero message sent')
            check(sql("SELECT COUNT(*) FROM messages WHERE message='0'") == '1','zero message saved')
            check(bob.request('login.php')[0] == 200,'authenticated login redirects')
            for path in ['index.php','profile.php','edit_profile.php','friend_list.php?search=eve','chat.php?user_id=3','add_post.php']:
                html = bob.request(path)[1]
                forms = re.findall(r'<form\b[^>]*method="post"[^>]*>(.*?)</form>', html, re.I | re.S)
                check(bool(forms) and all('name="csrf_token"' in form for form in forms),'POST forms include CSRF: '+path)
            from audit_cases import run, throttle
            run(Client, sql, check, app, artifacts)
            setup = subprocess.run(['php', str(app/'setup_admin.php')], env=env, capture_output=True, text=True, creationflags=flags, check=True)
            admin_password = re.search(r'Password: (.+)', setup.stdout).group(1).strip()
            again = subprocess.run(['php', str(app/'setup_admin.php')], env=env, capture_output=True, text=True, creationflags=flags, check=True)
            check('Password unchanged' in again.stdout, 'admin setup is idempotent')
            from admin_cases import run as run_admin
            run_admin(Client, sql, check, admin_password)
            if args.ui:
                import sys
                browser_result = subprocess.run([sys.executable, str(ROOT / 'tests' / 'ui_browser.py'), base, admin_password], capture_output=True, text=True, encoding='utf-8', creationflags=flags)
                print(browser_result.stdout, flush=True)
                if browser_result.returncode:
                    (artifacts / 'browser-failure.txt').write_text(browser_result.stderr, encoding='utf-8')
                    print(browser_result.stderr, flush=True)
                    raise AssertionError('Browser checks failed')
            throttle(Client, check)
            (artifacts / 'integration-results.json').write_text(json.dumps({'passed': checks, 'checks': passed}, indent=2), encoding='utf-8')
            print(str(checks)+' integration checks passed.', flush=True)
        except Exception:
            log.flush()
            print((temp/'servers.log').read_text(encoding='utf-8',errors='replace')[-12000:])
            raise
        finally:
            if len(processes) > 0:
                # MySQL on Windows can launch a child process. Shut down through SQL
                # and wait for it to release InnoDB files before removing test data.
                try:
                    sql('SHUTDOWN', False)
                except Exception:
                    pass
                deadline = time.monotonic() + 20
                while time.monotonic() < deadline:
                    try:
                        with socket.create_connection(('127.0.0.1', db_port), .2):
                            time.sleep(.2)
                    except OSError:
                        break
                time.sleep(1)
            for process in reversed(processes):
                process.terminate()
                try: process.wait(timeout=15)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait()
            log.close()
if __name__ == '__main__': main()
