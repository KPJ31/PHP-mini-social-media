"""Additional destructive cases run ONLY against integration.py's temporary database."""
import json, re, time, statistics
from pathlib import Path

def run(Client, sql, check, app, artifacts):
    results = {}
    user = Client()
    user.request('login.php')
    check(user.request('login.php',dict(email='bob@example.test',password='wrong-password'))[0] == 200,'invalid login renders generic error')
    check(user.request('login.php',dict(email='bob@example.test',password='Test-password-123'))[0] == 200,'audit user authenticates')
    anon = Client()
    anon.request('register.php')
    weak = anon.request('register.php',dict(username='weak',email='weak@example.test',password='123',confirm_password='123'))
    check('at least 8' in weak[1],'short registration password rejected')
    check('Passwords do not match' in anon.request('register.php',dict(username='mismatch',email='mm@example.test',password='Valid-password',confirm_password='different'))[1],'mismatched passwords rejected')
    check('already taken' in anon.request('register.php',dict(username='bob',email='bob@example.test',password='Valid-password',confirm_password='Valid-password'))[1],'duplicate registration rejected')
    check(anon.request('like_post.php',{'post_id':'1'})[0] == 401,'anonymous mutation rejected')
    check('Post content cannot be empty' in user.request('add_post.php',{'content':'   '})[1],'whitespace-only post rejected')
    check('too long' in user.request('add_post.php',{'content':'x'*65536})[1],'oversized post rejected')
    check(user.request('send_message.php',{'receiver_id':'3','message':'  '},True)[0] == 422,'empty message rejected')
    check(user.request('send_request.php',{'user_id':'2'})[0] == 422,'self friend request rejected')
    check(user.request('send_request.php',{'user_id':'999999'})[0] == 404,'unknown friend request target rejected')
    for path in ['profile.php?user_id[]=1','comment_post.php?post_id=not-an-id','profile.php?user_id=-1']:
        check(user.request(path)[0] == 404,'malformed identifier: '+path)
    check(user.request('friend_list.php?search[]=x')[0] == 200,'array search input handled')
    check(user.request('edit_profile.php',dict(update_profile='1',username='bob',email='not-email',bio=''))[0] == 200,'invalid profile email handled')
    # PNG fixture already uploaded for Alice was deleted with her account.
    check(len(list((app/'uploads').glob('*.png'))) == 0,'account deletion removes uploaded avatar')
    png = __import__('base64').b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
    profile = dict(update_profile='1',username='bob',email='bob@example.test',bio='Bio')
    user.upload('edit_profile.php',profile,'profile_image','one.png',png)
    old = sql('SELECT profile_image FROM users WHERE id=2')
    check((app/'uploads'/old).exists(),'avatar saved on disk')
    user.upload('edit_profile.php',profile,'profile_image','two.png',png)
    current = sql('SELECT profile_image FROM users WHERE id=2')
    check(current != old and not (app/'uploads'/old).exists(),'replaced avatar cleaned up')
    check('valid JPG' in user.upload('add_post.php',{'content':'bad file'},'image','large.png',png+b'x'*(2*1024*1024))[1],'oversized upload rejected')
    # Stable ordering and bounded pages with more rows than each limit.
    sql("INSERT INTO posts(user_id,content) VALUES "+','.join("(2,'audit-post-"+str(i)+"')" for i in range(65)))
    first = user.request('index.php')[1]
    second = user.request('index.php?page=2')[1]
    check(first.count('class="btn btn-outline-primary btn-sm like-button"') == 20,'feed page bounded at 20 posts')
    check('page=2' in first and 'page=3' in second,'feed pagination links')
    check('audit-post-64' in first and 'audit-post-64' not in second,'feed pagination changes content')
    check(user.request('index.php?page[]=bad')[0] == 200,'malformed pagination handled')
    post_id = sql('SELECT MAX(id) FROM posts')
    sql("INSERT INTO comments(post_id,user_id,comment) VALUES "+','.join("("+post_id+",2,'audit-comment-"+str(i)+"')" for i in range(55)))
    comments = user.request('comment_post.php?post_id='+post_id)[1]
    check('audit-comment-49' in comments and 'audit-comment-50' not in comments,'comments bounded at 50')
    check('audit-comment-50' in user.request('comment_post.php?post_id='+post_id+'&page=2')[1],'older comments reachable')
    cid = sql('SELECT MIN(id) FROM comments WHERE post_id='+post_id)
    user.request('delete_comment.php',{'id':cid,'post_id':'999999'})
    check(sql('SELECT COUNT(*) FROM comments WHERE id='+cid) == '0','comment deletion uses stored parent')
    sql("INSERT INTO messages(sender_id,receiver_id,message) VALUES "+','.join("(2,3,'audit-message-"+str(i)+"')" for i in range(110)))
    messages = user.request('load_chat_messages.php?receiver_id=3')[1]
    check(messages.count('class="message-bubble') == 100,'chat fetch bounded at 100')
    check('Older messages' in messages and 'audit-message-0<' not in messages,'older messages navigation shown')
    before = re.search(r'&before=(\d+)',messages)[1]
    check('audit-message-0<' in user.request('chat.php?user_id=3&before='+before)[1],'older message history reachable')
    check('Delete for me' in messages,'message deletion accessible in active chat UI')
    # Account deletion in a second browser invalidates a previously authenticated session.
    stale = Client()
    stale.register('stale')
    stale_id = sql("SELECT id FROM users WHERE username='stale'")
    sql('DELETE FROM users WHERE id='+stale_id)
    check('Welcome back' in stale.request('profile.php')[1],'deleted account session revoked')
    # Unexpected database failures are logged, never dumped into HTML.
    sql('RENAME TABLE posts TO audit_hidden_posts')
    try:
        code, body = user.request('index.php')
        check(code == 500 and body == 'Something went wrong. Please try again.','database exception returns safe 500')
    finally: sql('RENAME TABLE audit_hidden_posts TO posts')
    # Read-only local timings, not a production concurrency benchmark.
    sql("INSERT INTO posts(user_id,content) VALUES "+','.join("(2,'performance-fixture')" for _ in range(500)))
    timings = {}
    for path in ['index.php','profile.php','friend_list.php','load_chat_messages.php?receiver_id=3']:
        samples=[]
        for _ in range(10):
            start=time.perf_counter()
            check(user.request(path)[0] == 200,'timed response '+path)
            samples.append((time.perf_counter()-start)*1000)
        timings[path]={'samples':len(samples),'median_ms':round(statistics.median(samples),2),'max_ms':round(max(samples),2)}
    (artifacts/'performance.json').write_text(json.dumps(timings,indent=2))
    # Leave a small, readable dataset for the UI screenshots.
    sql("DELETE FROM posts WHERE content LIKE 'audit-post-%' OR content='performance-fixture'")
    sql("DELETE FROM messages WHERE message LIKE 'audit-message-%'")
    # The test deliberately removed likes FKs earlier: check cleanup didn't leave orphan rows.
    check(sql('SELECT COUNT(*) FROM likes l LEFT JOIN users u ON u.id=l.user_id LEFT JOIN posts p ON p.id=l.post_id WHERE u.id IS NULL OR p.id IS NULL') == '0','no orphaned likes after lifecycle operations')

def throttle(Client, check):
    client=Client()
    client.request('login.php')
    status=0
    for _ in range(22):
        status,_=client.request('login.php',dict(email='unknown@example.test',password='wrong-password'))
        if status==429: break
    check(status==429,'login rate limit enforced')
    other=Client()
    other.request('login.php')
    check(other.request('login.php',dict(email='unknown@example.test',password='wrong-password'))[0] == 429,'new session cannot bypass IP rate limit')
