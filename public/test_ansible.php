<?php
// Simulate Ansible output
// 1. Text with colors
echo "\x1b[32mTASK [Gathering Facts]\x1b[0m\n";
echo "\x1b[0;32mok: [localhost]\x1b[0m\n";

// 2. Clear Line (Progress Bar simulation)
echo "Downloading... 10%\r";
echo "\x1b[K"; // Clear line
echo "Downloading... 50%\r";
echo "\x1b[K";
echo "Downloading... 100%\n";

// 3. Reset with [m
echo "\x1b[31mError Message\x1b[m Back to normal\n";
