import app from 'flarum/admin/app';

app.initializers.add('tapao-line-notification', () => {
  app.extensionData
    .for('tapao-line-notification')

    // ─── LINE API Credentials ─────────────────────────────────
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

    // ─── Flex Message Branding ────────────────────────────────
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
    });
});
