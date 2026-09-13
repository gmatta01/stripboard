/**
 * StripBoard Admin JS Tests
 *
 * @package StripBoard
 */

describe('StripBoard Admin', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div id="stripboard-settings">
        <form id="stripboard-form">
          <input type="checkbox" name="gutenberg" checked />
          <input type="checkbox" name="block_widgets" />
          <input type="checkbox" name="comments" checked />
          <button type="submit">Save</button>
        </form>
        <div id="stripboard-notice" style="display:none;"></div>
      </div>
    `;
  });

  test('form elements exist', () => {
    const form = document.getElementById('stripboard-form');
    expect(form).toBeTruthy();
    expect(form.querySelectorAll('input[type="checkbox"]').length).toBe(3);
  });

  test('checkboxes have correct names', () => {
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    const names = Array.from(checkboxes).map(cb => cb.name);
    expect(names).toContain('gutenberg');
    expect(names).toContain('block_widgets');
    expect(names).toContain('comments');
  });

  test('checkboxes retain checked state', () => {
    const gutenberg = document.querySelector('input[name="gutenberg"]');
    const blockWidgets = document.querySelector('input[name="block_widgets"]');
    expect(gutenberg.checked).toBe(true);
    expect(blockWidgets.checked).toBe(false);
  });

  test('notice element exists and is hidden by default', () => {
    const notice = document.getElementById('stripboard-notice');
    expect(notice).toBeTruthy();
    expect(notice.style.display).toBe('none');
  });
});
