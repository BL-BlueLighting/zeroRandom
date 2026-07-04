<?php
/**
 * zero Random - Shared Footer
 */
?>
	</main>
	<footer class="site-footer">
	    <div class="footer-inner">
	        <div class="footer-brand">
	            <span class="logo-icon">🎰</span>
	            <span><i>zero</i> Random v<?= APP_VERSION ?></span>
	        </div>
	        <div class="footer-links">
	            <?php if (Session::isLoggedIn()): ?>
	                <a href="<?= url('/portfolio.php') ?>">我的持仓</a>
	                <a href="<?= url('/transactions.php') ?>">交易记录</a>
	            <?php endif; ?>
	            <a href="<?= url('/market.php') ?>">股票市场</a>
	            <a href="<?= url('/gacha.php') ?>">扭蛋抽卡</a>
	            <a href="<?= url('/ranking.php') ?>">排行榜</a>
	        </div>
	        <div class="footer-info">
	            <span>&copy; Copyright <?= date('Y') ?> BL.BlueLighting &middot; Powered by SQLite</span>
	        </div>
	    </div>
	</footer>

	<script src="assets/js/app.js"></script>
	</body>
</html>
