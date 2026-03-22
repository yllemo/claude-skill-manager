<?php
// Prevent direct access to content directory
http_response_code(403);
header('Location: ../');
exit('Access denied');