const express = require('express');
const router  = express.Router();

router.get('/', (req, res) => {
  res.render('roland', {
    pageTitle: 'Roland GO:KEYS - Piano Tracker',
    currentPage: 'roland',
  });
});

module.exports = router;
