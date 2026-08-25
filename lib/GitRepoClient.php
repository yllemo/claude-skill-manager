<?php
declare(strict_types=1);

/**
 * GitRepoClient — API-wrapper för GitHub och GitLab
 *
 * Baserad på https://github.com/yllemo/php-git-simple
 * Utökad med binärt filstöd (base64) för .skill-arkiv.
 *
 * GitHub:  Personal Access Token (PAT) eller Fine-grained token
 * GitLab:  Project Access Token eller Personal Access Token
 */
class GitRepoClient
{
    private string $provider;
    private string $token;
    private string $owner;
    private string $repo;
    private string $branch;
    private string $baseUrl;
    private string $projectId = '';

    public function __construct(
        string $provider,
        string $token,
        string $owner,
        string $repo,
        string $branch = 'main',
        string $gitlabUrl = 'https://gitlab.com'
    ) {
        $this->provider = strtolower(trim($provider));
        $this->token    = $token;
        $this->owner    = trim($owner);
        $this->repo     = trim($repo);
        $this->branch   = trim($branch) !== '' ? trim($branch) : 'main';

        if ($this->provider === 'github') {
            $this->baseUrl = 'https://api.github.com';
        } elseif ($this->provider === 'gitlab') {
            $this->baseUrl   = rtrim($gitlabUrl !== '' ? $gitlabUrl : 'https://gitlab.com', '/') . '/api/v4';
            $this->projectId = rawurlencode($this->owner . '/' . $this->repo);
        } else {
            throw new InvalidArgumentException("Provider måste vara 'github' eller 'gitlab'.");
        }
    }

    /** @return list<array{name:string,path:string,type:string,sha:string,size:int}> */
    public function listFiles(string $path = ''): array
    {
        if ($this->provider === 'github') {
            return $this->githubListFiles($path);
        }
        return $this->gitlabListFiles($path);
    }

    /** @return array{content:string,sha:string,encoding:string,name:string,path:string} */
    public function getFile(string $path): array
    {
        if ($this->provider === 'github') {
            return $this->githubGetFile($path);
        }
        return $this->gitlabGetFile($path);
    }

    /**
     * Skapa eller uppdatera en fil.
     * $binary=true skickar innehållet som base64 (krävs för .skill / zip).
     *
     * @return array<string, mixed>
     */
    public function putFile(
        string $path,
        string $content,
        string $commitMessage,
        ?string $sha = null,
        bool $binary = false
    ): array {
        if ($this->provider === 'github') {
            return $this->githubPutFile($path, $content, $commitMessage, $sha);
        }
        return $this->gitlabPutFile($path, $content, $commitMessage, $sha, $binary);
    }

    /** @return array<string, mixed> */
    public function deleteFile(string $path, string $commitMessage, string $sha = ''): array
    {
        if ($this->provider === 'github') {
            return $this->githubDeleteFile($path, $commitMessage, $sha);
        }
        return $this->gitlabDeleteFile($path, $commitMessage);
    }

    /** @return array<string, mixed> */
    public function getRepoInfo(): array
    {
        if ($this->provider === 'github') {
            return $this->request('GET', "/repos/{$this->owner}/{$this->repo}");
        }
        return $this->request('GET', "/projects/{$this->projectId}");
    }

    /** @return list<string> */
    public function listBranches(): array
    {
        if ($this->provider === 'github') {
            $data = $this->request('GET', "/repos/{$this->owner}/{$this->repo}/branches");
            return array_map(static fn(array $b): string => (string)$b['name'], $data);
        }
        $data = $this->request('GET', "/projects/{$this->projectId}/repository/branches");
        return array_map(static fn(array $b): string => (string)$b['name'], $data);
    }

    public function setBranch(string $branch): void
    {
        $this->branch = $branch;
    }

    public function getBranch(): string
    {
        return $this->branch;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    /** @return list<array{name:string,path:string,type:string,sha:string,size:int}> */
    private function githubListFiles(string $path): array
    {
        $endpoint = "/repos/{$this->owner}/{$this->repo}/contents/" . ltrim($path, '/');
        $endpoint .= '?ref=' . rawurlencode($this->branch);
        $data = $this->request('GET', $endpoint);

        if (!is_array($data)) {
            return [];
        }
        if (isset($data['type'])) {
            $data = [$data];
        }

        return array_map(static function (array $item): array {
            return [
                'name' => (string)($item['name'] ?? ''),
                'path' => (string)($item['path'] ?? ''),
                'type' => (($item['type'] ?? '') === 'dir') ? 'dir' : 'file',
                'sha'  => (string)($item['sha'] ?? ''),
                'size' => (int)($item['size'] ?? 0),
            ];
        }, $data);
    }

    /** @return array{content:string,sha:string,encoding:string,name:string,path:string} */
    private function githubGetFile(string $path): array
    {
        $endpoint = "/repos/{$this->owner}/{$this->repo}/contents/" . ltrim($path, '/');
        $endpoint .= '?ref=' . rawurlencode($this->branch);
        $data = $this->request('GET', $endpoint);

        $content = '';
        if (isset($data['content'])) {
            $content = (string)base64_decode(str_replace(["\n", "\r"], '', (string)$data['content']), true);
        }

        return [
            'content'  => $content,
            'sha'      => (string)($data['sha'] ?? ''),
            'encoding' => (string)($data['encoding'] ?? 'base64'),
            'name'     => (string)($data['name'] ?? basename($path)),
            'path'     => (string)($data['path'] ?? $path),
        ];
    }

    /** @return array<string, mixed> */
    private function githubPutFile(string $path, string $content, string $message, ?string $sha): array
    {
        $endpoint = "/repos/{$this->owner}/{$this->repo}/contents/" . ltrim($path, '/');
        $body = [
            'message' => $message,
            'content' => base64_encode($content),
            'branch'  => $this->branch,
        ];
        if ($sha !== null && $sha !== '') {
            $body['sha'] = $sha;
        }
        return $this->request('PUT', $endpoint, $body);
    }

    /** @return array<string, mixed> */
    private function githubDeleteFile(string $path, string $message, string $sha): array
    {
        $endpoint = "/repos/{$this->owner}/{$this->repo}/contents/" . ltrim($path, '/');
        return $this->request('DELETE', $endpoint, [
            'message' => $message,
            'sha'     => $sha,
            'branch'  => $this->branch,
        ]);
    }

    /** @return list<array{name:string,path:string,type:string,sha:string,size:int}> */
    private function gitlabListFiles(string $path): array
    {
        $query = http_build_query([
            'path'     => $path,
            'ref'      => $this->branch,
            'per_page' => 100,
        ]);
        $data = $this->request('GET', "/projects/{$this->projectId}/repository/tree?{$query}");
        if (!is_array($data)) {
            return [];
        }

        return array_map(static function (array $item): array {
            return [
                'name' => (string)($item['name'] ?? ''),
                'path' => (string)($item['path'] ?? ''),
                'type' => (($item['type'] ?? '') === 'tree') ? 'dir' : 'file',
                'sha'  => (string)($item['id'] ?? ''),
                'size' => 0,
            ];
        }, $data);
    }

    /** @return array{content:string,sha:string,encoding:string,name:string,path:string} */
    private function gitlabGetFile(string $path): array
    {
        $encodedPath = rawurlencode($path);
        $endpoint    = "/projects/{$this->projectId}/repository/files/{$encodedPath}?ref=" . rawurlencode($this->branch);
        $data        = $this->request('GET', $endpoint);

        $content = '';
        if (isset($data['content'])) {
            $content = (string)base64_decode((string)$data['content'], true);
        }

        return [
            'content'  => $content,
            'sha'      => (string)($data['blob_id'] ?? ''),
            'encoding' => (string)($data['encoding'] ?? 'base64'),
            'name'     => (string)($data['file_name'] ?? basename($path)),
            'path'     => (string)($data['file_path'] ?? $path),
        ];
    }

    /** @return array<string, mixed> */
    private function gitlabPutFile(
        string $path,
        string $content,
        string $message,
        ?string $sha,
        bool $binary
    ): array {
        $encodedPath = rawurlencode($path);
        $exists      = false;
        $endpoint    = "/projects/{$this->projectId}/repository/files/{$encodedPath}?ref=" . rawurlencode($this->branch);
        try {
            $this->request('GET', $endpoint);
            $exists = true;
        } catch (RuntimeException) {
            // 404 = ny fil
        }

        $body = [
            'branch'         => $this->branch,
            'commit_message' => $message,
        ];
        if ($binary) {
            $body['content']  = base64_encode($content);
            $body['encoding'] = 'base64';
        } else {
            $body['content']  = $content;
            $body['encoding'] = 'text';
        }

        $method = $exists ? 'PUT' : 'POST';
        return $this->request($method, "/projects/{$this->projectId}/repository/files/{$encodedPath}", $body);
    }

    /** @return array<string, mixed> */
    private function gitlabDeleteFile(string $path, string $message): array
    {
        $encodedPath = rawurlencode($path);
        return $this->request('DELETE', "/projects/{$this->projectId}/repository/files/{$encodedPath}", [
            'branch'         => $this->branch,
            'commit_message' => $message,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $body = []): array
    {
        $url     = $this->baseUrl . $endpoint;
        $headers = $this->buildHeaders();

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Kunde inte initiera cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'SkillManager-GitRepoClient/1.0 PHP',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body !== []) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
                }
                break;
            case 'PUT':
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
                if ($body !== []) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
                }
                break;
        }

        $response   = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new RuntimeException('cURL-fel: ' . $curlError);
        }

        $decoded = json_decode(is_string($response) ? $response : '', true);

        if ($statusCode >= 400) {
            $message = is_array($decoded)
                ? (string)($decoded['message'] ?? $decoded['error'] ?? $response)
                : (string)$response;
            throw new RuntimeException("API-fel {$statusCode}: {$message}");
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<string> */
    private function buildHeaders(): array
    {
        $headers = ['Content-Type: application/json'];
        if ($this->provider === 'github') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
            $headers[] = 'Accept: application/vnd.github+json';
            $headers[] = 'X-GitHub-Api-Version: 2022-11-28';
        } else {
            $headers[] = 'PRIVATE-TOKEN: ' . $this->token;
        }
        return $headers;
    }
}
