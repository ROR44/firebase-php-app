<?php

namespace Kreait\Firebase;

use Kreait\Firebase\Auth\ApiClient;
use Kreait\Firebase\Auth\CustomTokenGenerator;
use Kreait\Firebase\Auth\IdTokenVerifier;
use Kreait\Firebase\Auth\User;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;
use Kreait\Firebase\Util\JSON;
use Lcobucci\JWT\Token;
use Psr\Http\Message\ResponseInterface;

class Auth
{
    /**
     * @var ApiClient
     */
    private $client;

    /**
     * @var CustomTokenGenerator
     */
    private $customToken;

    /**
     * @var IdTokenVerifier
     */
    private $idTokenVerifier;

    public function __construct(ApiClient $client, CustomTokenGenerator $customToken, IdTokenVerifier $idTokenVerifier)
    {
        $this->client = $client;
        $this->customToken = $customToken;
        $this->idTokenVerifier = $idTokenVerifier;
    }

    public function getApiClient(): ApiClient
    {
        return $this->client;
    }

    public function getUser($uid, array $claims = []): User
    {
        $response = $this->client->exchangeCustomTokenForIdAndRefreshToken(
            $this->createCustomToken($uid, $claims)
        );

        return $this->convertResponseToUser($response);
    }

    public function listUsers(int $maxResults = 1000, int $batchSize = 1000): \Generator
    {
        $pageToken = null;
        $count = 0;

        do {
            $response = $this->client->downloadAccount($batchSize, $pageToken);
            $result = JSON::decode((string) $response->getBody(), true);

            foreach ((array) ($result['users'] ?? []) as $userData) {
                yield $userData;

                if (++$count === $maxResults) {
                    return;
                }
            }

            $pageToken = $result['nextPageToken'] ?? null;
        } while ($pageToken);
    }

    public function createUserWithEmailAndPassword(string $email, string $password): User
    {
        $this->client->signupNewUser($email, $password);

        // The response for a created user only includes the local id,
        // so we have to refetch them.
        return $this->getUserByEmailAndPassword($email, $password);
    }

    public function getUserByEmailAndPassword(string $email, string $password): User
    {
        $response = $this->client->getUserByEmailAndPassword($email, $password);

        return $this->convertResponseToUser($response);
    }

    public function createAnonymousUser(): User
    {
        $response = $this->client->signupNewUser();

        // The response for a created user only includes the local id,
        // so we have to refetch them.
        $uid = JSON::decode((string) $response->getBody(), true)['localId'];

        return $this->getUser($uid);
    }

    public function changeUserPassword(User $user, string $newPassword): User
    {
        $response = $this->client->changeUserPassword($user, $newPassword);

        return $this->convertResponseToUser($response);
    }

    public function changeUserEmail(User $user, string $newEmail): User
    {
        $response = $this->client->changeUserEmail($user, $newEmail);

        return $this->convertResponseToUser($response);
    }

    public function deleteUser($userOrUserId)
    {
        $uid = $userOrUserId instanceof User ? $userOrUserId->getUid() : (string) $userOrUserId;

        $this->client->deleteUser($uid);
    }

    public function sendEmailVerification(User $user)
    {
        $this->client->sendEmailVerification($user);
    }

    public function sendPasswordResetEmail($userOrEmail)
    {
        $email = $userOrEmail instanceof User
            ? $userOrEmail->getEmail()
            : (string) $userOrEmail;

        $this->client->sendPasswordResetEmail($email);
    }

    public function createCustomToken($uid, array $claims = [], \DateTimeInterface $expiresAt = null): Token
    {
        return $this->customToken->create($uid, $claims, $expiresAt);
    }

    /**
     * Verifies a JWT auth token. Returns a Promise with the tokens claims. Rejects the promise if the token
     * could not be verified. If checkRevoked is set to true, verifies if the session corresponding to the
     * ID token was revoked. If the corresponding user's session was invalidated, a RevokedToken
     * exception is thrown. If not specified the check is not applied.
     *
     * @param Token|string $idToken the JWT to verify
     * @param bool $checkIfRevoked whether to check if the ID token is revoked
     *
     * @throws RevokedIdToken
     *
     * @return Token the verified token
     */
    public function verifyIdToken($idToken, bool $checkIfRevoked = false): Token
    {
        $verifiedToken = $this->idTokenVerifier->verify($idToken);

        if ($checkIfRevoked) {
            $response = $this->client->getAccountInfo($verifiedToken->getClaim('sub'));
            $data = JSON::decode($response->getBody()->getContents(), true);

            if ($data['users'][0]['validSince'] ?? null) {
                $validSince = (int) $data['users'][0]['validSince'];
                $tokenAuthenticatedAt = (int) $verifiedToken->getClaim('auth_time');

                if ($tokenAuthenticatedAt < $validSince) {
                    throw new RevokedIdToken($verifiedToken);
                }
            }
        }

        return $verifiedToken;
    }

    /**
     * Revokes all refresh tokens for the specified user identified by the uid provided.
     * In addition to revoking all refresh tokens for a user, all ID tokens issued
     * before revocation will also be revoked on the Auth backend. Any request with an
     * ID token generated before revocation will be rejected with a token expired error.
     *
     * @param User|string $userOrUid the user whose tokens are to be revoked
     *
     * @return string the user id of the corresponding user
     */
    public function revokeRefreshTokens($userOrUid): string
    {
        $this->client->revokeRefreshTokens($uid = $this->uid($userOrUid));

        return $uid;
    }

    private function convertResponseToUser(ResponseInterface $response): User
    {
        $data = JSON::decode((string) $response->getBody(), true);

        return User::create($data['idToken'], $data['refreshToken']);
    }

    private function uid($userOrUid): string
    {
        if ($userOrUid instanceof User) {
            return $userOrUid->getUid();
        }

        return (string) $userOrUid;
    }
}
