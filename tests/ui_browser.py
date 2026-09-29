"""Browser checks against the disposable app started by integration.py --ui."""
import base64, os, sys, tempfile
from pathlib import Path
sys.path.insert(0, str(Path(tempfile.gettempdir()) / 'minisocial-ui-tools'))
from playwright.sync_api import sync_playwright, expect

base = sys.argv[1]
artifacts = Path(__file__).resolve().parent / 'artifacts' / 'ui'
artifacts.mkdir(parents=True, exist_ok=True)
errors = []
accessibility = []
import json

checks = 0
def check(value, label):
    global checks
    assert value, label
    checks += 1
    print('UI PASS: '+label, flush=True)
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe', headless=True)
    context = browser.new_context(viewport={'width':1440,'height':1000}, reduced_motion='reduce')
    context.route('**/*', lambda route: route.continue_() if route.request.url.startswith(base) else route.abort())
    page = context.new_page()
    page.on('pageerror', lambda error: errors.append(str(error)))
    def visit(path):
        response = page.goto(base+path, wait_until='networkidle')
        assert response.status == 200, path
        page.evaluate('document.fonts.ready')
    def accessibility_check(label):
        page.evaluate((Path(__file__).resolve().parent / 'vendor' / 'axe.min.js').read_text(encoding='utf-8'))
        result = page.evaluate("async () => await axe.run(document, {runOnly: {type:'tag', values:['wcag2a','wcag2aa','wcag21aa']}})")
        accessibility.append({'page': label, 'violations': [{'id':v['id'],'impact':v['impact'],'description':v['description'],'nodes':[{'target':n['target'],'summary':n.get('failureSummary','')} for n in v['nodes']]} for v in result['violations']]})
    def layout(label):
        check(page.locator('h1').count() == 1, label+' has one main heading')
        check(page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), label+' has no page overflow')
        check(page.evaluate("""() => [...document.querySelectorAll('input:not([type=hidden]), textarea')].every(el => el.labels?.length || el.getAttribute('aria-label'))"""), label+' fields have labels')
    for width in [320,390,768,1440]:
        page.set_viewport_size({'width':width,'height':1000})
        visit('login.php'); layout('login '+str(width))
        if width in [390,1440]: accessibility_check('login '+str(width))
        if width in [390,1440]: page.screenshot(path=str(artifacts/('login-'+str(width)+'.png')), full_page=True)
        visit('register.php'); layout('register '+str(width))
        if width in [390,1440]: accessibility_check('register '+str(width))
    visit('login.php')
    page.locator('#email').fill('bob@example.test')
    page.locator('#password').fill('Test-password-123')
    page.locator('[data-password-toggle=password]').click()
    check(page.locator('#password').get_attribute('type') == 'text','password visibility toggle')
    page.get_by_role('button',name='Log in',exact=True).click()
    page.wait_for_url('**/index.php')
    visit('add_post.php')
    page.locator('#content').fill('A small moment worth sharing.\nHere is to good conversations and everyday connections.')
    image = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
    page.locator('#image').set_input_files({'name':'preview.png','mimeType':'image/png','buffer':image})
    check(page.locator('#post-preview').is_visible(),'image preview')
    page.locator('#image').set_input_files([])
    page.get_by_role('button',name='Publish post').click()
    page.wait_for_url('**/index.php')
    comment_path = page.locator('a[href^="comment_post.php?"]').first.get_attribute('href')
    paths=['index.php','profile.php','friend_list.php?search=eve','chat.php?user_id=3','add_post.php','edit_profile.php',comment_path]
    for width in [320,390,768,1440,1920]:
        page.set_viewport_size({'width':width,'height':1000})
        for path in paths:
            visit(path)
            layout(path+' '+str(width))
            if width in [390,1440]: accessibility_check(path+' '+str(width))
            if width in [390,1440]:
                page.screenshot(path=str(artifacts/(path.split('?')[0].replace('.php','')+'-'+str(width)+'.png')), full_page=True)
        if width < 992:
            page.locator('.menu-toggle').click()
            check(page.locator('#site-navigation').is_visible(),'mobile menu visible '+str(width))
            check(page.locator('.menu-toggle').get_attribute('aria-expanded') == 'true','mobile menu state '+str(width))
            page.keyboard.press('Escape')
            check(page.locator('.menu-toggle').evaluate('(el) => el === document.activeElement'),'Escape restores focus '+str(width))
    page.set_viewport_size({'width':1440,'height':1000})
    visit('index.php')
    page.locator('.like-button').first.click()
    expect(page.locator('.like-button').first).to_contain_text('1 likes')
    check(True,'like interaction after restyling')
    visit(comment_path)
    page.locator('#comment').fill('Glad to be part of this community.')
    page.get_by_role('button',name='Post Comment',exact=True).click()
    page.wait_for_load_state('networkidle')
    check('Glad to be part of this community.' in page.locator('body').inner_text(),'comment form submission')
    visit('chat.php?user_id=3')
    page.locator('#message').fill('A quick hello from the refreshed MiniSocial.')
    page.get_by_role('button',name='Send',exact=True).click()
    expect(page.locator('#chat-box')).to_contain_text('A quick hello')
    check(True,'chat sends and refreshes')
    check(not errors,'no browser JavaScript errors: '+str(errors))
    page.locator('button[aria-label="Delete message for you"]').last.focus()
    page.wait_for_timeout(3300)
    check(page.evaluate("document.activeElement.getAttribute('aria-label') === 'Delete message for you'"), 'chat polling preserves keyboard focus')
    page.on('dialog', lambda dialog: dialog.dismiss())
    count = page.locator('.message-bubble').count()
    page.locator('button[aria-label="Delete message for you"]').last.click()
    check(page.locator('.message-bubble').count() == count, 'destructive action cancellation')
    if len(sys.argv)>2:
        context.clear_cookies()
        visit('login.php')
        page.locator('#email').fill('admin@mini.com')
        page.locator('#password').fill(sys.argv[2])
        page.get_by_role('button',name='Log in',exact=True).click()
        page.wait_for_url('**/admin.php')
        for width in [320,390,768,1440,1920]:
            page.set_viewport_size({'width':width,'height':1000})
            for tab in ['overview','users','posts','comments','messages','audit']:
                visit('admin.php?tab='+tab)
                layout('admin '+tab+' '+str(width))
                if width in [390,1440]: accessibility_check('admin '+tab+' '+str(width))
                if tab in ['overview','users'] and width in [390,1440]:
                    page.screenshot(path=str(artifacts/('admin-'+tab+'-'+str(width)+'.png')),full_page=True)
        visit('admin.php?tab=users&q=mini_admin')
        page.get_by_text('Edit account',exact=True).click()
        check(page.locator('input[name=username]').is_visible(),'admin edit account expands')
        page.locator('textarea[name=bio]').fill('Community administrator')
        page.get_by_role('button',name='Save account',exact=True).click()
        page.wait_for_load_state('networkidle')
        check('Changes saved successfully' in page.locator('body').inner_text(),'admin account form saves')
    (artifacts/'accessibility.json').write_text(json.dumps(accessibility,indent=2),encoding='utf-8')
    (artifacts/'browser-results.json').write_text(json.dumps({'passed':checks,'javascript_errors':errors,'axe_pages':len(accessibility),'axe_violations':sum(len(x['violations']) for x in accessibility)},indent=2))
    check(not any(x['violations'] for x in accessibility), 'automated WCAG A/AA accessibility checks')
    context.close()
    browser.close()
print(str(checks)+' browser checks passed. Screenshots: '+str(artifacts), flush=True)
