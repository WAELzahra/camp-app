{{-- resources/views/emails/notification.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $data['title'] }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #0E3D38;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #0E3D38;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $data['title'] }}</h1>
    </div>
    
    <div class="content">
        <p>{{ $data['content'] }}</p>
        
        @if(isset($data['action_url']) && isset($data['action_text']))
            <a href="{{ $data['action_url'] }}" class="button">{{ $data['action_text'] }}</a>
        @endif
    </div>
    
    <div class="footer">
        <p>&copy; {{ date('Y') }} TunisiaCamp. All rights reserved.</p>
    </div>
</body>
</html>