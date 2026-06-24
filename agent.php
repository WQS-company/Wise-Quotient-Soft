<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My ElevenLabs Voice Agent</title>

<style>
    body {
        background: #eef2f7;
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .wrapper {
        width: 420px;
        background: white;
        padding: 25px;
        border-radius: 14px;
        box-shadow: 0 0 25px rgba(0,0,0,0.15);
        text-align: center;
    }

    h2 {
        margin-top: 0;
        color: #333;
    }

    p {
        color: #444;
        font-size: 14px;
        margin-bottom: 20px;
    }

    .agent-box {
        margin-top: 20px;
    }
</style>
</head>

<body>

<div class="wrapper">
    <div class="agent-box">
        <!-- ✅ Your ElevenLabs Voice Agent Widget -->
        <elevenlabs-convai 
            agent-id="agent_6501kb25c2m4fg1amme369s14wry" 
            style="width:100%;">
        </elevenlabs-convai>
    </div>
</div>

<!-- Widget script -->
<script 
    src="https://unpkg.com/@elevenlabs/convai-widget-embed" 
    async 
    type="text/javascript">
</script>

</body>
</html>
