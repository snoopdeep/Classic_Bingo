// import typescript from '@rollup/plugin-typescript';
// import { nodeResolve } from '@rollup/plugin-node-resolve';

// export default {
//   // Use your main entry file
//   input: 'js/app.ts',
//   output: {
//     // Output the file exactly where your HTML expects it
//     file: 'js/app.js',
//     // 'iife' format wraps the code so it runs correctly in the browser
//     format: 'iife',
//     sourcemap: true
//   },
//   plugins: [
//     nodeResolve(), // Helps Rollup find imported modules
//     typescript({ tsconfig: './tsconfig.json' }) // Use your tsconfig
//   ]
// };

import typescript from '@rollup/plugin-typescript';
import { nodeResolve } from '@rollup/plugin-node-resolve';

export default {
  input: 'js/app.ts',
  output: {
    file: 'js/app.js',
    format: 'es',  // ← CHANGE THIS from 'iife' to 'es'
    sourcemap: true
  },
  plugins: [
    nodeResolve(),
    typescript({ tsconfig: './tsconfig.json' })
  ]
};