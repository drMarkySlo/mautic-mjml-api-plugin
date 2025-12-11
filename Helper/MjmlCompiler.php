<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMjmlApiBundle\Helper;

/**
 * MJML Compiler Helper
 * 
 * Compiles MJML to HTML using the system's MJML binary or Node.js
 */
class MjmlCompiler
{
    /**
     * Compile MJML to HTML
     * 
     * @param string $mjml MJML source code
     * @return string|null Compiled HTML or original MJML on fallback
     */
    public function compile(string $mjml): ?string
    {
        try {
            // Option 1: Use exec to call mjml CLI if available (Preferred method)
            if ($this->isMjmlCliAvailable()) {
                return $this->compileWithCli($mjml);
            }
            
            // Option 2: Try to compile using Node.js directly (Fallback)
            // This is useful if 'mjml' is not in global PATH but 'node' is available
            $nodeCompiled = $this->compileWithNode($mjml);
            if ($nodeCompiled) {
                return $nodeCompiled;
            }
            
            // Option 3: Return MJML as-is
            // Mautic will interpret this as raw HTML or compile it later if configured
            return $mjml;
            
        } catch (\Exception $e) {
            // Log error silently and return original content to prevent breaking the API flow
            error_log('MJML compilation failed: ' . $e->getMessage());
            return $mjml;
        }
    }
    
    /**
     * Check if MJML CLI is available in the system
     */
    private function isMjmlCliAvailable(): bool
    {
        $output = [];
        $returnVar = 0;
        // Check using 'which' command (Linux/Unix)
        exec('which mjml 2>/dev/null', $output, $returnVar);
        
        return $returnVar === 0 && !empty($output);
    }
    
    /**
     * Compile MJML using the CLI command
     */
    private function compileWithCli(string $mjml): ?string
    {
        // Create temporary file for input
        $tmpFile = tempnam(sys_get_temp_dir(), 'mjml_');
        if ($tmpFile === false) {
            return null;
        }
        
        file_put_contents($tmpFile, $mjml);
        
        // Compile with mjml CLI 
        // -s: stdout output
        // stderr redirected to /dev/null to keep output clean
        $output = [];
        $returnVar = 0;
        exec("mjml {$tmpFile} -s 2>/dev/null", $output, $returnVar);
        
        // Clean up temporary file
        unlink($tmpFile);
        
        if ($returnVar === 0 && !empty($output)) {
            $html = implode("\n", $output);
            
            // Remove MJML CLI comment line if present (often appears at the top)
            $html = preg_replace('/^<!-- FILE: .* -->\n/', '', $html);
            
            return $html;
        }
        
        return null;
    }
    
    /**
     * Compile MJML using Node.js script execution
     * 
     * This acts as a fallback method that creates a temporary Node.js script
     * to require the mjml package directly.
     */
    private function compileWithNode(string $mjml): ?string
    {
        // Verify node is available first
        $output = [];
        $returnVar = 0;
        exec('which node 2>/dev/null', $output, $returnVar);
        
        if ($returnVar !== 0) {
            return null;
        }

        // Create temporary files
        $inputFile = tempnam(sys_get_temp_dir(), 'mjml_input_');
        $outputFile = tempnam(sys_get_temp_dir(), 'mjml_output_');
        
        if ($inputFile === false || $outputFile === false) {
            return null;
        }

        file_put_contents($inputFile, $mjml);
        
        // Create a temporary Node.js script to perform the compilation
        $nodeScript = <<<JS
const fs = require('fs');

try {
    // Try to require mjml. If globally installed, NODE_PATH might need to be set,
    // or this script assumes 'npm link mjml' or similar setup.
    const mjml2html = require('mjml');
    const mjml = fs.readFileSync('{$inputFile}', 'utf8');
    const result = mjml2html(mjml);

    if (result.errors.length > 0) {
        // We log errors to stderr but don't fail hard, 
        // we might still get usable HTML
        console.error(JSON.stringify(result.errors));
    }

    fs.writeFileSync('{$outputFile}', result.html);
} catch (e) {
    console.error(e);
    process.exit(1);
}
JS;
        
        $scriptFile = tempnam(sys_get_temp_dir(), 'mjml_script_') . '.js';
        file_put_contents($scriptFile, $nodeScript);
        
        // Execute Node.js script
        $output = [];
        $returnVar = 0;
        exec("node {$scriptFile} 2>&1", $output, $returnVar);
        
        // Read result
        $html = null;
        if ($returnVar === 0 && file_exists($outputFile)) {
            $content = file_get_contents($outputFile);
            if ($content !== false) {
                $html = $content;
            }
        }
        
        // Clean up all temporary files
        if (file_exists($inputFile)) unlink($inputFile);
        if (file_exists($scriptFile)) unlink($scriptFile);
        if (file_exists($outputFile)) unlink($outputFile);
        
        return $html;
    }
}