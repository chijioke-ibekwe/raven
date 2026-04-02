<?php

namespace ChijiokeIbekwe\Raven\Jobs;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;

class EncryptedRavenChannelJob extends RavenChannelJob implements ShouldBeEncrypted {}
