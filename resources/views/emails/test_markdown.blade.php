@component('mail::message')
# Hello!

This is a **test email** sent using **Markdown** formatting.

- You can use bullet lists
- **Bold text**
- _Italic text_
- [Links](https://rozeskin.com/tracking/?tracking_id=5100308838)

@component('mail::button', ['url' => 'https://rozeskin.com/tracking/?tracking_id=5100308838'])
Visit Laravel
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
