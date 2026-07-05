import app from 'flarum/admin/app';
import buildSettingComponent from 'flarum/admin/utils/buildSettingComponent';

export { default as extend } from './extend';

app.initializers.add('tapao-line-notification', () => {
  app.registry
    .for('tapao-line-notification')
    .registerSetting({
      setting: 'tapao-line-notification.loginChannelId',
      label: app.translator.trans('tapao-line-notification.admin.settings.line_login_channel_id_label'),
      help: app.translator.trans('tapao-line-notification.admin.settings.line_login_channel_id_help'),
      type: 'text',
    })
    .registerSetting({
      setting: 'tapao-line-notification.loginChannelSecret',
      label: app.translator.trans('tapao-line-notification.admin.settings.line_login_channel_secret_label'),
      help: app.translator.trans('tapao-line-notification.admin.settings.line_login_channel_secret_help'),
      type: 'password',
    })
    .registerSetting({
      setting: 'tapao-line-notification.messagingAccessToken',
      label: app.translator.trans('tapao-line-notification.admin.settings.line_messaging_token_label'),
      help: app.translator.trans('tapao-line-notification.admin.settings.line_messaging_token_help'),
      type: 'password',
    })
    .registerSetting({
      setting: 'tapao-line-notification.messagingChannelSecret',
      label: app.translator.trans('tapao-line-notification.admin.settings.line_messaging_channel_secret_label'),
      help: app.translator.trans('tapao-line-notification.admin.settings.line_messaging_channel_secret_help'),
      type: 'password',
    })
    .registerSetting({
      setting: 'tapao-line-notification.flexHeaderColor',
      label: app.translator.trans('tapao-line-notification.admin.settings.flex_header_color_label'),
      help: app.translator.trans('tapao-line-notification.admin.settings.flex_header_color_help'),
      type: 'color-preview',
      placeholder: '#06C755',
    })
    .registerSetting({
      setting: 'tapao-line-notification.flexButtonColor',
      label: app.translator.trans('tapao-line-notification.admin.settings.flex_button_color_label'),
      help: app.translator.trans('tapao-line-notification.admin.settings.flex_button_color_help'),
      type: 'color-preview',
      placeholder: '#06C755',
    })
    .registerSetting({
      setting: 'tapao-line-notification.flexTitleColor',
      label: app.translator.trans('tapao-line-notification.admin.settings.flex_title_color_label'),
      help: app.translator.trans('tapao-line-notification.admin.settings.flex_title_color_help'),
      type: 'color-preview',
      placeholder: '#111111',
    })
    .registerSetting({
      setting: 'tapao-line-notification.useFirstImageAsThumbnail',
      label: app.translator.trans('tapao-line-notification.admin.settings.use_first_image_as_thumbnail_label'),
      help: app.translator.trans('tapao-line-notification.admin.settings.use_first_image_as_thumbnail_help'),
      type: 'boolean',
    })
    .registerSetting(buildSettingComponent(function () {
      const disabledTypesStream = this.setting('tapao-line-notification.disabledNotificationTypes');
      const currentDisabled = (disabledTypesStream() || '').split(',').map(s => s.trim()).filter(Boolean);
      const availableTypes = app.data.lineNotificationTypes || [];

      return (
        <div className="Form-group">
          <label>{app.translator.trans('tapao-line-notification.admin.settings.disabled_notification_types_label')}</label>
          <div className="helpText" style={{ marginBottom: '10px' }}>
            {app.translator.trans('tapao-line-notification.admin.settings.disabled_notification_types_help')}
          </div>
          {availableTypes.map(type => {
            const isChecked = currentDisabled.includes(type);
            const translationMap = {
              postMentioned: 'flarum-mentions.forum.settings.notify_post_mentioned_label',
              userMentioned: 'flarum-mentions.forum.settings.notify_user_mentioned_label',
              postLiked: 'flarum-likes.forum.settings.notify_post_liked_label',
              newPost: 'flarum-subscriptions.forum.settings.notify_new_post_label',
            };
            const translationKey = translationMap[type] || `tapao-line-notification.lib.line_message.notification_${type}`;
            const label = app.translator.translations[translationKey] ? app.translator.trans(translationKey) : type;

            return (
              <div style={{ marginBottom: '5px' }}>
                <label className="checkbox">
                  <input
                    type="checkbox"
                    checked={isChecked}
                    onchange={e => {
                      let nextDisabled;
                      if (e.target.checked) {
                        nextDisabled = [...currentDisabled, type];
                      } else {
                        nextDisabled = currentDisabled.filter(t => t !== type);
                      }
                      disabledTypesStream(nextDisabled.join(','));
                    }}
                  />
                  {label}
                </label>
              </div>
            );
          })}
          {availableTypes.length === 0 && (
            <p className="helpText">No notification types registered.</p>
          )}
        </div>
      );
    }));
});
