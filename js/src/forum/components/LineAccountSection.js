import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Alert from 'flarum/common/components/Alert';
import Stream from 'flarum/common/utils/Stream';

/**
 * LineAccountSection
 *
 * Renders into the Account section of Settings:
 *  - When not connected: description + "Connect LINE" button
 *  - When connected:     "Connected as {displayName}" info + "Disconnect" button
 */
export default class LineAccountSection extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading    = Stream(false);
    this.alert      = Stream(null); // { type: 'success'|'error', message: string }

    // Check for redirect params (after OAuth callback)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('line_linked') === '1') {
      this.alert({ type: 'success', message: app.translator.trans('tapao-line-notification.forum.settings.line_link_success') });
      // Clean up URL
      window.history.replaceState({}, '', window.location.pathname);
    } else if (urlParams.get('line_error')) {
      this.alert({ type: 'error', message: app.translator.trans('tapao-line-notification.forum.settings.line_error', {
        error: urlParams.get('line_error'),
      }) });
      window.history.replaceState({}, '', window.location.pathname);
    }
  }

  view() {
    const user         = app.session.user;
    const lineUserId   = user.attribute('lineUserId');
    const displayName  = user.attribute('lineDisplayName');
    const isConnected  = !!lineUserId;
    const isLoading    = this.loading();
    const alert        = this.alert();

    return (
      <fieldset className="SettingsPage-line">
        <legend>
          <i className="fab fa-line" />
          {' '}
          {app.translator.trans('tapao-line-notification.forum.settings.line_section_heading')}
        </legend>

        {alert && (
          <Alert type={alert.type} dismissible onDismiss={() => this.alert(null)}>
            {alert.message}
          </Alert>
        )}

        {isConnected ? (
          <div className="Form-group">
            <p>
              <span className="LineAccountSection-statusDot connected" />
              {' '}
              {app.translator.trans('tapao-line-notification.forum.settings.line_connected_as', { name: displayName })}
            </p>
            <Button
              className="Button Button--danger"
              loading={isLoading}
              onclick={this.disconnect.bind(this)}
            >
              {app.translator.trans('tapao-line-notification.forum.settings.line_disconnect_button')}
            </Button>
          </div>
        ) : (
          <div className="Form-group">
            <p className="helpText">
              {app.translator.trans('tapao-line-notification.forum.settings.line_linking_description')}
            </p>
            <Button
              className="Button Button--primary LineAccountSection-btn--line"
              loading={isLoading}
              onclick={this.connect.bind(this)}
            >
              <i className="fab fa-line" />
              {' '}
              {app.translator.trans('tapao-line-notification.forum.settings.line_connect_button')}
            </Button>
          </div>
        )}
      </fieldset>
    );
  }

  /**
   * Redirect user to LINE OAuth authorize page.
   */
  connect() {
    window.location.href = app.forum.attribute('apiUrl') + '/line/connect';
  }

  /**
   * Call DELETE /api/line/disconnect and refresh current user data.
   */
  async disconnect() {
    if (!confirm(app.translator.trans('tapao-line-notification.forum.settings.line_disconnect_confirm'))) {
      return;
    }

    this.loading(true);
    m.redraw();

    try {
      await app.request({
        method: 'DELETE',
        url: app.forum.attribute('apiUrl') + '/line/disconnect',
      });

      // Refresh user attributes from server
      await app.store.find('users', app.session.user.id());

      this.alert({ type: 'success', message: app.translator.trans('tapao-line-notification.forum.settings.line_disconnect_success') });
    } catch (e) {
      this.alert({ type: 'error', message: e.message || 'An error occurred.' });
    } finally {
      this.loading(false);
      m.redraw();
    }
  }
}

