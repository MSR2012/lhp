<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Reminder</title>
    <style>
        body { font-family: sans-serif; background: #f9fafb; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background: #1d4ed8; padding: 24px 32px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; }
        .body { padding: 32px; color: #374151; line-height: 1.6; }
        .event-name { font-size: 22px; font-weight: 700; color: #111827; margin-bottom: 8px; }
        .badge { display: inline-block; background: #dbeafe; color: #1d4ed8; padding: 4px 12px; border-radius: 9999px; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
        .detail { margin: 6px 0; font-size: 15px; }
        .label { font-weight: 600; color: #6b7280; }
        .footer { padding: 20px 32px; background: #f3f4f6; font-size: 13px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Don't forget — your event is coming up!</h1>
        </div>
        <div class="body">
            <p>Hi {{ $attendee->name }},</p>

            <div class="badge">
                @if($window === '24hours') Tomorrow @else In 3 days @endif
            </div>

            <div class="event-name">{{ $event->payload['name'] ?? 'Event' }}</div>

            @if(!empty($event->payload['venue']['name']))
            <p class="detail"><span class="label">Venue:</span> {{ $event->payload['venue']['name'] }}</p>
            @endif

            @if($event->address)
            <p class="detail"><span class="label">Location:</span> {{ $event->address }}</p>
            @endif

            @if($event->createdTime)
            <p class="detail">
                <span class="label">Date:</span>
                {{ \Illuminate\Support\Carbon::createFromTimestamp($event->createdTime)->format('D, M j, Y g:i A') }}
                @if($event->timezone) ({{ $event->timezone }})@endif
            </p>
            @endif

            <p style="margin-top: 24px;">We can't wait to see you there!</p>
        </div>
        <div class="footer">
            You received this email because you're registered for this event. &copy; {{ date('Y') }}
        </div>
    </div>
</body>
</html>
