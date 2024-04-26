<main class="landing">
    <form action="/" method="post">
        <h1 class="title">
            Enter Anilist Username:
        </h1>
        <input type="text" autofocus placeholder="blekmus" name="username">
    </form>

    <?php if ($error): ?>
        <p class="press press-red">
            <?php if ($username): ?>
                <?= $error ?>. <a href="/<?= $username ?>">Profile</a> or <a href="/logout">Logout</a>
            <?php else: ?>
                <?= $error ?>. <a href="<?= $url ?>">Login</a>
            <?php endif; ?>
        </p>
    <?php elseif ($username): ?>
        <p class="press">Welcome back
            <?= $username ?>. <a href="/<?= $username ?>">Profile</a> or <a href="/logout">Logout</a>
        </p>
    <?php else: ?>
        <p class="press">Press Enter to search or <a href="<?= $url ?>">Login</a></p>
    <?php endif; ?>
</main>